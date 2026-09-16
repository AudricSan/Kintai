<?php

/**
 * CLI provisioner for Kintai — creates a new instance from scratch.
 *
 * Usage:
 *   php scripts/provision.php \
 *       --admin-email=admin@example.com \
 *       --admin-password=secret \
 *       [--admin-first-name=Admin] \
 *       [--admin-last-name=Demo] \
 *       [--db-driver=sqlite|mysql] \
 *       [--db-host=127.0.0.1] \
 *       [--db-port=3306] \
 *       [--db-name=kintai] \
 *       [--db-user=root] \
 *       [--db-pass=] \
 *       [--db-prefix=kt_] \
 *       [--seed] \
 *       [--force]
 *
 * Environment variables (same names, uppercase, DB_ prefix) are used as fallback.
 */

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));

require BASE_PATH . '/vendor/autoload.php';
require BASE_PATH . '/src/Core/helpers.php';

use Illuminate\Database\Capsule\Manager as Capsule;
use kintai\Core\Application;
use kintai\Core\Database\MigrationRunner;
use kintai\Core\Repositories\UserRepositoryInterface;

// ─── Parse CLI options ───────────────────────────────────────────────────

$opts = getopt('', [
    'admin-email:',
    'admin-password:',
    'admin-first-name:',
    'admin-last-name:',
    'db-driver:',
    'db-host:',
    'db-port:',
    'db-name:',
    'db-user:',
    'db-pass:',
    'db-prefix:',
    'seed',
    'force',
]);

$adminEmail    = $opts['admin-email']    ?: env('ADMIN_EMAIL', '');
$adminPassword = $opts['admin-password']  ?: env('ADMIN_PASSWORD', '');
$adminFirstName = $opts['admin-first-name'] ?: env('ADMIN_FIRST_NAME', 'Admin');
$adminLastName  = $opts['admin-last-name']  ?: env('ADMIN_LAST_NAME', 'Kintai');
$dbDriver      = $opts['db-driver']      ?: env('DB_DRIVER', 'sqlite');
$dbHost        = $opts['db-host']        ?: env('DB_HOST', '127.0.0.1');
$dbPort        = (int) ($opts['db-port'] ?? env('DB_PORT', '3306'));
$dbName        = $opts['db-name']        ?: env('DB_DATABASE', 'kintai');
$dbUser        = $opts['db-user']        ?: env('DB_USERNAME', 'root');
$dbPass        = $opts['db-pass']        ?: env('DB_PASSWORD', '');
$dbPrefix      = $opts['db-prefix']      ?: env('DB_PREFIX', '');
$runSeeds      = isset($opts['seed']);
$force         = isset($opts['force']);

// ─── Guards ───────────────────────────────────────────────────────────────

if ($adminEmail === '' || $adminPassword === '') {
    fwrite(STDERR, "Erreur : --admin-email et --admin-password sont requis.\n");
    exit(1);
}

if (!in_array($dbDriver, ['sqlite', 'mysql'], true)) {
    fwrite(STDERR, "Erreur : driver invalide. Utilise sqlite ou mysql.\n");
    exit(1);
}

if (file_exists(BASE_PATH . '/storage/installed.lock') && !$force) {
    fwrite(STDERR, "Erreur : instance déjà installée. Utilise --force pour réinstaller.\n");
    exit(1);
}

// ─── Helpers ──────────────────────────────────────────────────────────────

function pdo_dsn_without_db(string $driver, string $host, int $port): string
{
    return match ($driver) {
        'mysql' => sprintf('mysql:host=%s;port=%d;charset=utf8mb4', $host, $port),
        default => throw new InvalidArgumentException("Unsupported driver: $driver"),
    };
}

function pdo_options(): array
{
    return [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
}

function run_seeds(PDO $pdo, string $dir): void
{
    $files = glob($dir . '/*.sql') ?: [];
    sort($files);
    foreach ($files as $file) {
        $sql = trim((string) file_get_contents($file));
        if ($sql !== '') {
            $pdo->exec($sql);
        }
    }
}

// ─── Step 1 : Storage directories ────────────────────────────────────────

foreach ([BASE_PATH . '/storage/app', BASE_PATH . '/storage/logs', BASE_PATH . '/storage/backups'] as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
}

// ─── Step 2 : Create database (MySQL only) ──────────────────────────────

if ($dbDriver === 'mysql') {
    $serverPdo = new PDO(
        pdo_dsn_without_db('mysql', $dbHost, $dbPort),
        $dbUser,
        $dbPass,
        pdo_options()
    );
    $serverPdo->exec(
        "CREATE DATABASE IF NOT EXISTS `{$dbName}` "
        . "CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
    );
    unset($serverPdo);
    echo "[Kintai] Base de données MySQL '{$dbName}' créée.\n";
}

// ─── Step 3 : Write config/database.local.php ────────────────────────────

$localCfg = ['driver' => $dbDriver, 'connections' => []];

if ($dbDriver === 'mysql') {
    $localCfg['connections']['mysql'] = [
        'host'      => $dbHost,
        'port'      => $dbPort,
        'database'  => $dbName,
        'username'  => $dbUser,
        'password'  => $dbPass,
        'charset'   => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
        'prefix'    => $dbPrefix,
    ];
}

file_put_contents(
    BASE_PATH . '/config/database.local.php',
    '<?php return ' . var_export($localCfg, true) . ';' . PHP_EOL
);
echo "[Kintai] config/database.local.php écrit.\n";

// ─── Step 4 : Boot framework, run migrations ────────────────────────────

$app = new Application(BASE_PATH);
$app->boot();

$runner = new MigrationRunner($app);
$runner->run();
echo "[Kintai] Migrations exécutées.\n";

// ─── Step 5 : Seeders ────────────────────────────────────────────────────

if ($runSeeds) {
    $pdo = $app->container()->make(Capsule::class)->getConnection()->getPdo();
    run_seeds($pdo, BASE_PATH . '/database/seeds/' . $dbDriver);
    unset($pdo);
    echo "[Kintai] Seeders exécutés.\n";
}

// ─── Step 6 : Create admin user ──────────────────────────────────────────

$userRepo = $app->container()->make(UserRepositoryInterface::class);

$userRepo->save([
    'first_name'    => $adminFirstName,
    'last_name'     => $adminLastName,
    'display_name'  => $adminFirstName . ' ' . $adminLastName,
    'email'         => $adminEmail,
    'password_hash' => password_hash($adminPassword, PASSWORD_BCRYPT, ['cost' => 12]),
    'is_admin'      => 1,
    'is_active'     => 1,
    'created_at'    => date('Y-m-d H:i:s'),
    'updated_at'    => date('Y-m-d H:i:s'),
]);
echo "[Kintai] Admin '{$adminEmail}' créé.\n";

// ─── Step 7 : Write version.json ─────────────────────────────────────────

$versionPath = BASE_PATH . '/storage/app/version.json';
$versionData = [
    'version'      => env('APP_VERSION', '2.0.0'),
    'installed_at' => date('Y-m-d H:i:s'),
    'updated_at'   => date('Y-m-d H:i:s'),
];
file_put_contents($versionPath, json_encode($versionData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

// ─── Step 8 : Lock installation ─────────────────────────────────────────

file_put_contents(BASE_PATH . '/storage/installed.lock', bin2hex(random_bytes(32)));
echo "[Kintai] Installation verrouillée.\n";

echo "[Kintai] Instance prête !\n";
exit(0);
