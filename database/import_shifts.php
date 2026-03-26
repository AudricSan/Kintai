<?php
/**
 * Script d'import des anciens shifts vers la nouvelle DB SQLite.
 *
 * Usage :
 *   php database/import_shifts.php [options]
 *
 * Options :
 *   --dry-run          Affiche les opérations sans les exécuter
 *   --store-id=N       ID du store cible (défaut : 1)
 *   --skip-existing    Ignore les shifts déjà présents (comparaison user+date+start+end)
 *   --year=YYYY        Importer seulement cette année
 *   --month=MM         Importer seulement ce mois (requiert --year)
 *   --timezone=TZ      Timezone du store (défaut : Asia/Tokyo)
 */

declare(strict_types=1);

// ─── Configuration ────────────────────────────────────────────────────────────

$ROOT       = dirname(__DIR__);
$OLD_DIR    = $ROOT . '/database/OLD/shifts';
$STAFF_DIR  = $ROOT . '/database/OLD/staff';
$DB_PATH    = $ROOT . '/storage/app/database.sqlite';
$STAFF_IDX  = $STAFF_DIR . '/index.json';

// ─── Arguments CLI ────────────────────────────────────────────────────────────

$opts = getopt('', ['dry-run', 'store-id:', 'skip-existing', 'year:', 'month:', 'timezone:']);

$dryRun       = isset($opts['dry-run']);
$storeId      = (int) ($opts['store-id'] ?? 1);
$skipExisting = isset($opts['skip-existing']);
$filterYear   = isset($opts['year'])  ? str_pad((string) $opts['year'],  4, '0', STR_PAD_LEFT) : null;
$filterMonth  = isset($opts['month']) ? str_pad((string) $opts['month'], 2, '0', STR_PAD_LEFT) : null;
$timezone     = $opts['timezone'] ?? 'Asia/Tokyo';

if ($filterMonth !== null && $filterYear === null) {
    log_error('--month requiert --year');
    exit(1);
}

// ─── Helpers ──────────────────────────────────────────────────────────────────

function log_info(string $msg): void  { echo "\033[36m[INFO]\033[0m  $msg\n"; }
function log_ok(string $msg): void    { echo "\033[32m[OK]\033[0m    $msg\n"; }
function log_skip(string $msg): void  { echo "\033[33m[SKIP]\033[0m  $msg\n"; }
function log_warn(string $msg): void  { echo "\033[33m[WARN]\033[0m  $msg\n"; }
function log_error(string $msg): void { echo "\033[31m[ERR]\033[0m   $msg\n"; }

/** "HH:MM" → minutes (supporte les heures > 24, ex: "30:00" = 1800 min) */
function parseMinutes(string $hhmm): int
{
    [$h, $m] = explode(':', $hhmm . ':0');
    return (int) $h * 60 + (int) $m;
}

/**
 * Normalise une heure pouvant dépasser 24h (ex: "30:00" → "06:00", nextDay=true).
 * @return array{0: string, 1: bool}  [heure normalisée "HH:MM", est_jour_suivant]
 */
function normalizeTime(string $hhmm): array
{
    $totalMin = parseMinutes($hhmm);
    $nextDay  = $totalMin >= 1440;
    $norm     = $totalMin % 1440;
    return [sprintf('%02d:%02d', intdiv($norm, 60), $norm % 60), $nextDay];
}

/**
 * Calcule duration_minutes en tenant compte du cross_midnight et de la pause.
 * Accepte les heures normalisées (< 24h).
 */
function calcDuration(string $start, string $end, bool $crossMidnight, int $pauseMin): int
{
    $s = parseMinutes($start);
    $e = parseMinutes($end);
    $raw = ($crossMidnight || $e <= $s) ? (1440 - $s + $e) : ($e - $s);
    return max(0, $raw - $pauseMin);
}

/**
 * Convertit date + heure locale normalisée en timestamp UTC ISO 8601.
 * Si $nextDay = true, la date est décalée d'un jour.
 */
function toUtc(string $date, string $time, string $timezone, bool $nextDay = false): string
{
    $tz = new DateTimeZone($timezone);
    $dt = new DateTime("$date $time", $tz);
    if ($nextDay) {
        $dt->modify('+1 day');
    }
    $dt->setTimezone(new DateTimeZone('UTC'));
    return $dt->format('Y-m-d H:i:s');
}

/** Mappe le type textuel (ancien) vers shift_type_id (nouveau). */
function mapShiftTypeId(string $type): ?int
{
    return match (strtolower($type)) {
        'morning'   => 1,
        'afternoon' => 2,
        'night'     => 3,
        default     => null,   // 'day' et autres → NULL
    };
}

// ─── Vérifications préliminaires ──────────────────────────────────────────────

foreach ([$STAFF_IDX, $DB_PATH, $OLD_DIR] as $path) {
    if (!file_exists($path)) {
        log_error("Introuvable : $path");
        exit(1);
    }
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
    log_error('Connexion DB échouée : ' . $e->getMessage());
    exit(1);
}

// ─── Construction de la map staff_id (ancien) → user_id (nouveau) ─────────────
//
// Chaîne : ancien staff_id → staff_code (index.json) → user_id (store_user)

$staffIndex = json_decode(file_get_contents($STAFF_IDX), true);
if (!is_array($staffIndex)) {
    log_error('Impossible de parser index.json des staffs');
    exit(1);
}

// ancien id → staff_code
$idToCode = [];
foreach ($staffIndex as $entry) {
    $idToCode[(int) $entry['id']] = trim((string) ($entry['staff_code'] ?? ''));
}

// staff_code → user_id (dans le store cible)
$suRows = $pdo->prepare('SELECT user_id, staff_code FROM store_user WHERE store_id = ?');
$suRows->execute([$storeId]);
$codeToUserId = [];
foreach ($suRows->fetchAll() as $row) {
    $code = trim((string) ($row['staff_code'] ?? ''));
    if ($code !== '') {
        $codeToUserId[$code] = (int) $row['user_id'];
    }
}

// Map finale : ancien staff_id → user_id
$staffIdToUserId = [];
$unmappable = [];
foreach ($idToCode as $oldId => $code) {
    if ($code !== '' && isset($codeToUserId[$code])) {
        $staffIdToUserId[$oldId] = $codeToUserId[$code];
    } else {
        $unmappable[$oldId] = $code;
    }
}

log_info(sprintf(
    'Store cible : #%d | %d staffs mappés | %d non-mappables | Timezone : %s | Mode : %s',
    $storeId,
    count($staffIdToUserId),
    count($unmappable),
    $timezone,
    $dryRun ? 'DRY-RUN' : 'RÉEL'
));
if (!empty($unmappable)) {
    foreach ($unmappable as $id => $code) {
        log_warn("Staff ancien #$id (staff_code='$code') introuvable dans store_user → ignoré");
    }
}
echo str_repeat('─', 70) . "\n";

// ─── Compteurs ────────────────────────────────────────────────────────────────

$stats = ['inserted' => 0, 'skipped' => 0, 'errors' => 0, 'unmapped' => 0, 'files' => 0];

// ─── Préparation des requêtes ─────────────────────────────────────────────────

$stmtCheck = $pdo->prepare(
    'SELECT id FROM shifts
     WHERE store_id = ? AND user_id = ? AND shift_date = ? AND start_time = ? AND end_time = ?
       AND deleted_at IS NULL
     LIMIT 1'
);

$stmtInsert = $pdo->prepare(
    'INSERT INTO shifts
        (store_id, user_id, shift_date, start_time, end_time,
         shift_type_id, cross_midnight, pause_minutes, duration_minutes,
         starts_at, ends_at, notes, created_at)
     VALUES
        (:store_id, :user_id, :shift_date, :start_time, :end_time,
         :shift_type_id, :cross_midnight, :pause_minutes, :duration_minutes,
         :starts_at, :ends_at, NULL, datetime(\'now\'))'
);

// ─── Parcours des fichiers ────────────────────────────────────────────────────

$years = array_filter(scandir($OLD_DIR), fn($d) => ctype_digit($d) && strlen($d) === 4);
sort($years);

foreach ($years as $year) {
    if ($filterYear !== null && $year !== $filterYear) {
        continue;
    }

    $yearPath = "$OLD_DIR/$year";
    $months = array_filter(scandir($yearPath), fn($d) => ctype_digit($d) && strlen($d) === 2);
    sort($months);

    foreach ($months as $month) {
        if ($filterMonth !== null && $month !== $filterMonth) {
            continue;
        }

        $monthPath = "$yearPath/$month";
        $dayFiles  = array_filter(scandir($monthPath), fn($f) => str_ends_with($f, '.json'));
        sort($dayFiles);

        foreach ($dayFiles as $dayFile) {
            $filePath = "$monthPath/$dayFile";
            $stats['files']++;

            $data = json_decode(file_get_contents($filePath), true);
            if (!is_array($data) || empty($data['shifts'])) {
                continue;
            }

            $date   = $data['date'] ?? "$year-$month-" . pathinfo($dayFile, PATHINFO_FILENAME);
            $shifts = $data['shifts'];

            foreach ($shifts as $shift) {
                $oldStaffId  = (int) ($shift['staff_id'] ?? 0);
                $startTime   = $shift['start'] ?? '';
                $endTime     = $shift['end']   ?? '';
                $type        = $shift['type']  ?? 'day';
                $crossMidnight = (bool) ($shift['cross_midnight'] ?? false);
                $pauseStr    = $shift['pause_duration'] ?? '00:00';

                // Validation des champs minimaux
                if ($startTime === '' || $endTime === '') {
                    log_warn("[$date] Shift sans heure (staff_id=$oldStaffId) — ignoré");
                    continue;
                }

                // Résolution du user_id
                if (!isset($staffIdToUserId[$oldStaffId])) {
                    $stats['unmapped']++;
                    continue;
                }
                $userId = $staffIdToUserId[$oldStaffId];

                // Normaliser les heures (gère "30:00" etc.)
                [$startTime, $startNextDay] = normalizeTime($startTime);
                [$endTime,   $endNextDay]   = normalizeTime($endTime);
                // cross_midnight : flag du fichier OU end normalisé est J+1 OU end < start
                $crossMidnight = $crossMidnight || $endNextDay
                    || parseMinutes($endTime) <= parseMinutes($startTime);

                $pauseMin     = parseMinutes($pauseStr);
                $durationMin  = calcDuration($startTime, $endTime, $crossMidnight, $pauseMin);
                $shiftTypeId  = mapShiftTypeId($type);
                $startsAt     = toUtc($date, $startTime, $timezone, $startNextDay);
                $endsAt       = toUtc($date, $endTime,   $timezone, $endNextDay || $crossMidnight);
                $crossInt     = $crossMidnight ? 1 : 0;

                $label = "[{$date}] staff#{$oldStaffId}→user#{$userId} {$startTime}-{$endTime} ({$type})";

                if ($dryRun) {
                    log_info("[INSERT] $label pause={$pauseMin}min dur={$durationMin}min");
                    $stats['inserted']++;
                    continue;
                }

                // Vérifier doublon
                if ($skipExisting) {
                    $stmtCheck->execute([$storeId, $userId, $date, $startTime, $endTime]);
                    if ($stmtCheck->fetchColumn()) {
                        log_skip("$label — déjà présent");
                        $stats['skipped']++;
                        continue;
                    }
                }

                try {
                    $stmtInsert->execute([
                        ':store_id'         => $storeId,
                        ':user_id'          => $userId,
                        ':shift_date'       => $date,
                        ':start_time'       => $startTime,
                        ':end_time'         => $endTime,
                        ':shift_type_id'    => $shiftTypeId,
                        ':cross_midnight'   => $crossInt,
                        ':pause_minutes'    => $pauseMin,
                        ':duration_minutes' => $durationMin,
                        ':starts_at'        => $startsAt,
                        ':ends_at'          => $endsAt,
                    ]);
                    log_ok("$label — inséré (id=" . $pdo->lastInsertId() . ')');
                    $stats['inserted']++;
                } catch (PDOException $e) {
                    log_error("$label — " . $e->getMessage());
                    $stats['errors']++;
                }
            }
        }
    }
}

// ─── Résumé ───────────────────────────────────────────────────────────────────

echo str_repeat('─', 70) . "\n";
echo sprintf(
    "\033[1mRésumé :\033[0m  %d fichiers lus  |  %d insérés  |  %d ignorés  |  %d non-mappés  |  %d erreurs\n",
    $stats['files'], $stats['inserted'], $stats['skipped'], $stats['unmapped'], $stats['errors']
);

if ($dryRun) {
    echo "\n\033[33m[DRY-RUN] Aucune modification effectuée.\033[0m\n";
}
