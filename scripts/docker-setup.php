<?php

/**
 * Script d'installation automatique pour Docker / Render.
 * Réplique les étapes de public/install.php sans interface web.
 *
 * Variables d'environnement utilisées :
 *   ADMIN_FIRST_NAME  (défaut : Admin)
 *   ADMIN_LAST_NAME   (défaut : Demo)
 *   ADMIN_EMAIL       (défaut : admin@demo.local)
 *   ADMIN_PASSWORD    (défaut : kintai-demo)
 *   SEED_DEMO_DATA    (défaut : true) — exécute les seeders SQLite si true
 */

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));

require BASE_PATH . '/vendor/autoload.php';
require BASE_PATH . '/src/Core/helpers.php';

use kintai\Core\Application;
use kintai\Core\Repositories\UserRepositoryInterface;

// ─── Paramètres depuis l'environnement ────────────────────────────────────

$adminFirstName = (string) (getenv('ADMIN_FIRST_NAME') ?: 'Admin');
$adminLastName  = (string) (getenv('ADMIN_LAST_NAME')  ?: 'Demo');
$adminEmail     = (string) (getenv('ADMIN_EMAIL')      ?: 'admin@demo.local');
$adminPassword  = (string) (getenv('ADMIN_PASSWORD')   ?: 'kintai-demo');
$seedDemoData   = filter_var(getenv('SEED_DEMO_DATA') ?: 'true', FILTER_VALIDATE_BOOLEAN);

// ─── Étape 1 : Dossiers storage ───────────────────────────────────────────

foreach ([BASE_PATH . '/storage/app', BASE_PATH . '/storage/logs'] as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
}

// ─── Étape 2 : config/database.local.php (driver SQLite) ─────────────────

$localCfg = ['driver' => 'sqlite', 'connections' => []];
file_put_contents(
    BASE_PATH . '/config/database.local.php',
    '<?php return ' . var_export($localCfg, true) . ';' . PHP_EOL
);

// ─── Étape 3 : Migrations SQLite ──────────────────────────────────────────

$dbPath = BASE_PATH . '/storage/app/database.sqlite';
$pdo    = new PDO('sqlite:' . $dbPath, null, null, [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
]);

$migrationsDir = BASE_PATH . '/database/migrations/sqlite';
$all           = glob($migrationsDir . '/*.sql') ?: [];
$creates       = array_values(array_filter($all, fn($f) => str_starts_with(basename($f), 'create_')));
$alters        = array_values(array_filter($all, fn($f) => !str_starts_with(basename($f), 'create_')));
sort($creates);
sort($alters);

foreach (array_merge($creates, $alters) as $file) {
    $sql = trim((string) file_get_contents($file));
    if ($sql !== '') {
        $pdo->exec($sql);
    }
    try {
        $pdo->prepare('INSERT OR IGNORE INTO migrations (migration) VALUES (?)')->execute([basename($file)]);
    } catch (Throwable) {
        // Table de suivi pas encore dispo, on continue
    }
}

// ─── Étape 4 : Seeders (données de démo) ──────────────────────────────────

if ($seedDemoData) {
    echo '[Kintai] Chargement des données de démo...' . PHP_EOL;
    $pdo2      = new PDO('sqlite:' . $dbPath, null, null, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
    $seedsDir  = BASE_PATH . '/database/seeds/sqlite';
    $seedFiles = glob($seedsDir . '/*.sql') ?: [];
    sort($seedFiles);
    foreach ($seedFiles as $file) {
        $sql = trim((string) file_get_contents($file));
        if ($sql !== '') {
            $pdo2->exec($sql);
        }
        echo '[Kintai] Seeder exécuté : ' . basename($file) . PHP_EOL;
    }
    unset($pdo2);
    echo '[Kintai] Seeders terminés.' . PHP_EOL;
}

unset($pdo);

// ─── Étape 5 : Création de l'utilisateur admin via le framework ───────────

$app      = new Application(BASE_PATH);
$app->boot();
$userRepo = $app->container()->make(UserRepositoryInterface::class);

$userRepo->save([
    'first_name'    => $adminFirstName,
    'last_name'     => $adminLastName,
    'display_name'  => $adminFirstName . ' ' . $adminLastName,
    'email'         => $adminEmail,
    'password_hash' => password_hash($adminPassword, PASSWORD_BCRYPT),
    'is_admin'      => 1,
    'is_active'     => 1,
    'created_at'    => date('Y-m-d H:i:s'),
    'updated_at'    => date('Y-m-d H:i:s'),
]);

// ─── Étape 6 : Verrou d'installation ──────────────────────────────────────

file_put_contents(BASE_PATH . '/storage/installed.lock', (string) time());

echo '[Kintai] Admin créé : ' . $adminEmail . PHP_EOL;
