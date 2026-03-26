<?php
/**
 * Script d'import des anciens staffs vers la nouvelle DB SQLite.
 *
 * Usage :
 *   php database/import_staff.php [options]
 *
 * Options :
 *   --dry-run           Affiche les opérations sans les exécuter
 *   --store-id=N        ID du store cible (défaut : 1)
 *   --password=XXX      Mot de passe par défaut pour les nouveaux comptes (défaut : "0000")
 *   --skip-existing     Ne pas mettre à jour les users déjà existants (défaut : les mettre à jour)
 *   --create-store=NOM  Créer le store s'il n'existe pas (avec le nom donné)
 */

declare(strict_types=1);

// ─── Configuration ────────────────────────────────────────────────────────────

$ROOT      = dirname(__DIR__);
$OLD_DIR   = $ROOT . '/database/OLD/staff';
$DB_PATH   = $ROOT . '/storage/app/database.sqlite';
$INDEX     = $OLD_DIR . '/index.json';

// ─── Arguments CLI ────────────────────────────────────────────────────────────

$opts = getopt('', ['dry-run', 'store-id:', 'password:', 'skip-existing', 'create-store:']);

$dryRun       = isset($opts['dry-run']);
$storeId      = (int) ($opts['store-id'] ?? 1);
$defaultPass  = $opts['password'] ?? null;   // null = défaut "0000"
$skipExisting = isset($opts['skip-existing']);
$createStore  = $opts['create-store'] ?? null; // nom du store à créer si absent

// ─── Helpers ──────────────────────────────────────────────────────────────────

function log_info(string $msg): void  { echo "\033[36m[INFO]\033[0m  $msg\n"; }
function log_ok(string $msg): void    { echo "\033[32m[OK]\033[0m    $msg\n"; }
function log_skip(string $msg): void  { echo "\033[33m[SKIP]\033[0m  $msg\n"; }
function log_warn(string $msg): void  { echo "\033[33m[WARN]\033[0m  $msg\n"; }
function log_error(string $msg): void { echo "\033[31m[ERR]\033[0m   $msg\n"; }

function makeEmail(array $data): string
{
    if (!empty($data['email'])) {
        return trim($data['email']);
    }
    $code = !empty($data['staff_code']) ? $data['staff_code'] : $data['id'];
    return "staff_{$code}@store.local";
}

function makePassword(?string $defaultPass): string
{
    // Priorité : option CLI > défaut "0000"
    $plain = $defaultPass ?? '0000';
    return password_hash($plain, PASSWORD_BCRYPT, ['cost' => 12]);
}

/** Convertit role_id (ancien) en rôle texte (nouveau schéma). */
function mapRole(int $roleId): string
{
    return match ($roleId) {
        1 => 'admin',
        3 => 'manager',
        default => 'staff',
    };
}

// ─── Vérifications préliminaires ──────────────────────────────────────────────

if (!file_exists($INDEX)) {
    log_error("Fichier index introuvable : $INDEX");
    exit(1);
}

if (!file_exists($DB_PATH)) {
    log_error("Base de données introuvable : $DB_PATH");
    exit(1);
}

// ─── Connexion SQLite ─────────────────────────────────────────────────────────

try {
    $pdo = new PDO("sqlite:$DB_PATH", options: [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->exec('PRAGMA foreign_keys = ON;');
    $pdo->exec('PRAGMA journal_mode = WAL;');
} catch (PDOException $e) {
    log_error("Connexion DB échouée : " . $e->getMessage());
    exit(1);
}

// ─── Vérification / création du store cible ───────────────────────────────────

$storeRow = $pdo->prepare('SELECT id, name FROM stores WHERE id = ?');
$storeRow->execute([$storeId]);
$storeExists = $storeRow->fetch();

if (!$storeExists) {
    if ($createStore !== null) {
        $storeName = trim($createStore) ?: "Store #$storeId";
        if (!$dryRun) {
            $pdo->prepare(
                "INSERT INTO stores (id, name, timezone, currency, locale, created_at)
                 VALUES (?, ?, 'UTC', 'JPY', 'ja', datetime('now'))"
            )->execute([$storeId, $storeName]);
            log_ok("Store #$storeId créé : \"$storeName\"");
        } else {
            log_info("[DRY-RUN] Store #$storeId serait créé : \"$storeName\"");
        }
    } else {
        log_error("Le store #$storeId n'existe pas en base.");
        log_error("Stores disponibles :");
        $allStores = $pdo->query('SELECT id, name FROM stores')->fetchAll();
        if (empty($allStores)) {
            log_error("  (aucun store — lancez d'abord l'installateur ou utilisez --create-store=NOM)");
        } else {
            foreach ($allStores as $s) {
                log_error("  #{$s['id']} {$s['name']}");
            }
            log_error("Utilisez --store-id=N avec un ID valide ou --create-store=NOM pour créer le store.");
        }
        exit(1);
    }
}

// ─── Chargement des données OLD ───────────────────────────────────────────────

$index = json_decode(file_get_contents($INDEX), true);
if (!is_array($index)) {
    log_error("Impossible de parser index.json");
    exit(1);
}

log_info(sprintf("Store cible : #%d | %d staff dans l'index | Mode : %s",
    $storeId,
    count($index),
    $dryRun ? 'DRY-RUN' : 'RÉEL'
));
echo str_repeat('─', 70) . "\n";

// ─── Compteurs ────────────────────────────────────────────────────────────────

$stats = ['inserted' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => 0];

// ─── Import ───────────────────────────────────────────────────────────────────

foreach ($index as $entry) {
    $id   = (int) $entry['id'];
    $file = "$OLD_DIR/$id.json";

    // Fusionner index + fiche détaillée
    $data = $entry;
    if (file_exists($file)) {
        $detail = json_decode(file_get_contents($file), true);
        if (is_array($detail)) {
            $data = array_replace($data, $detail);
        }
    }

    // ── Champs mappés ──────────────────────────────────────────────────────

    // Champs obligatoires — "Code employé" accepte staff_code ou employee_code
    $staffCode    = trim((string) ($data['staff_code'] ?? $data['employee_code'] ?? ''));
    $displayName  = trim((string) ($data['display_name'] ?? $data['name'] ?? ''));
    $firstName    = trim((string) ($data['first_name'] ?? ''));
    $lastName     = trim((string) ($data['last_name'] ?? ''));
    $email        = makeEmail($data);
    $phone        = trim((string) ($data['phone'] ?? '')) ?: null;
    $profileImage = trim((string) ($data['profile_image'] ?? '')) ?: null;
    $color        = trim((string) ($data['color'] ?? '#3B82F6')) ?: '#3B82F6';
    $isActive     = ($data['is_active'] ?? true) && ($data['status'] ?? 'active') !== 'false' ? 1 : 0;

    // Fallback nom : display_name si first/last vides
    if ($firstName === '' && $lastName === '') {
        $firstName = $displayName;
        $lastName  = '';
    }

    // Validation des 6 champs obligatoires
    $missing = [];
    if ($displayName === '') $missing[] = 'display_name';
    if ($firstName   === '') $missing[] = 'first_name';
    if ($email       === '') $missing[] = 'email';
    if ($staffCode   === '') $missing[] = 'staff_code/employee_code';
    if (!empty($missing)) {
        log_warn("#$id — champs manquants [" . implode(', ', $missing) . "] — ligne ignorée");
        $stats['skipped']++;
        continue;
    }

    // Emploi
    $employment   = is_array($data['employment'] ?? null) ? $data['employment'] : [];
    $roleId       = (int) ($employment['role_id'] ?? 2);
    $role         = mapRole($roleId);
    $hireDate     = $employment['hire_date'] ?? null;
    $contractType = $employment['contract_type'] ?? null;
    $empStatus    = $employment['status'] ?? 'active';
    $suActive     = ($empStatus === 'active') ? 1 : 0;
    $isManager    = $role === 'manager' ? 1 : 0;
    $isAdmin      = $role === 'admin'   ? 1 : 0;

    // Taux horaires → JSON
    $salaryRates = null;
    if (isset($data['salary']['rates']) && is_array($data['salary']['rates'])) {
        $rates = $data['salary']['rates'];
        // Normaliser les clés (morning/afternoon/night uniquement)
        $normalized = [];
        foreach (['morning', 'afternoon', 'night', 'day'] as $k) {
            if (isset($rates[$k]) && $rates[$k] > 0) {
                $normalized[$k] = (float) $rates[$k];
            }
        }
        if (!empty($normalized)) {
            $salaryRates = json_encode($normalized, JSON_UNESCAPED_UNICODE);
        }
    }

    $label = "#$id $displayName ($staffCode)";

    // ── Vérifier si le user existe déjà ───────────────────────────────────

    $existing = $pdo->prepare('SELECT id, email FROM users WHERE email = ?');
    $existing->execute([$email]);
    $existingUser = $existing->fetch();

    if ($dryRun) {
        $action = $existingUser
            ? ($skipExisting ? 'SKIP' : 'UPDATE')
            : 'INSERT';
        log_info("[$action] $label → email=$email, role=$role, isActive=$isActive");
        continue;
    }

    try {
        $pdo->beginTransaction();

        if ($existingUser) {
            if ($skipExisting) {
                log_skip("$label — déjà présent (id={$existingUser['id']}), ignoré");
                $pdo->rollBack();
                $stats['skipped']++;
                continue;
            }

            // Mise à jour du user existant (sans toucher au mot de passe)
            $pdo->prepare(
                'UPDATE users SET
                    first_name = ?, last_name = ?, display_name = ?,
                    employee_code = ?, phone = ?, profile_image = ?, color = ?,
                    is_admin = ?, is_active = ?, updated_at = datetime(\'now\')
                 WHERE id = ?'
            )->execute([
                $firstName, $lastName, $displayName,
                $staffCode ?: null, $phone, $profileImage, $color,
                $isAdmin, $isActive,
                $existingUser['id'],
            ]);
            $userId = (int) $existingUser['id'];
            $action = 'mis à jour';

        } else {
            // Nouveau user → hash du mot de passe
            $hash = makePassword($defaultPass);

            $pdo->prepare(
                'INSERT INTO users
                    (email, password_hash, first_name, last_name, display_name,
                     phone, profile_image, color, is_admin, is_active, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, datetime(\'now\'))'
            )->execute([
                $email, $hash, $firstName, $lastName, $displayName,
                $phone, $profileImage, $color, $isAdmin, $isActive,
            ]);
            $userId = (int) $pdo->lastInsertId();
            $action = 'inséré';
        }

        // ── store_user (upsert) ────────────────────────────────────────────

        $su = $pdo->prepare('SELECT id FROM store_user WHERE store_id = ? AND user_id = ?');
        $su->execute([$storeId, $userId]);
        $existingSU = $su->fetchColumn();

        if ($existingSU) {
            $pdo->prepare(
                'UPDATE store_user SET
                    role = ?, staff_code = ?, hire_date = ?, contract_type = ?,
                    is_manager = ?, hourly_rates = ?, is_active = ?,
                    updated_at = datetime(\'now\')
                 WHERE store_id = ? AND user_id = ?'
            )->execute([
                $role, $staffCode ?: null, $hireDate, $contractType,
                $isManager, $salaryRates, $suActive,
                $storeId, $userId,
            ]);
        } else {
            $pdo->prepare(
                'INSERT INTO store_user
                    (store_id, user_id, role, staff_code, hire_date,
                     contract_type, is_manager, hourly_rates, is_active, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, datetime(\'now\'))'
            )->execute([
                $storeId, $userId, $role,
                $staffCode ?: null, $hireDate, $contractType,
                $isManager, $salaryRates, $suActive,
            ]);
        }

        $pdo->commit();

        if ($action === 'inséré') {
            log_ok("$label — $action (user_id=$userId, pass=" . ($defaultPass ?? '0000') . ")");
            $stats['inserted']++;
        } else {
            log_ok("$label — $action (user_id=$userId)");
            $stats['updated']++;
        }

    } catch (PDOException $e) {
        $pdo->rollBack();
        log_error("$label — " . $e->getMessage());
        $stats['errors']++;
    }
}

// ─── Résumé ───────────────────────────────────────────────────────────────────

echo str_repeat('─', 70) . "\n";
echo sprintf(
    "\033[1mRésumé :\033[0m  %d insérés  |  %d mis à jour  |  %d ignorés  |  %d erreurs\n",
    $stats['inserted'], $stats['updated'], $stats['skipped'], $stats['errors']
);

if ($dryRun) {
    echo "\n\033[33m[DRY-RUN] Aucune modification effectuée.\033[0m\n";
}
