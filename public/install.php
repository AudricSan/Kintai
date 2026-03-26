<?php

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));

require BASE_PATH . '/vendor/autoload.php';
require BASE_PATH . '/src/Core/helpers.php';

use kintai\Core\Application;
use kintai\Core\Repositories\UserRepositoryInterface;

// ─── Already installed ────────────────────────────────────────────────────────

if (file_exists(BASE_PATH . '/storage/installed.lock')) {
    header('Location: /');
    exit;
}

// ─── Helpers ──────────────────────────────────────────────────────────────────

function mysql_dsn_without_db(array $cfg): string
{
    return sprintf(
        'mysql:host=%s;port=%d;charset=%s',
        $cfg['host'],
        (int) ($cfg['port'] ?? 3306),
        $cfg['charset'] ?? 'utf8mb4'
    );
}

function mysql_dsn_with_db(array $cfg): string
{
    return sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=%s',
        $cfg['host'],
        (int) ($cfg['port'] ?? 3306),
        $cfg['database'],
        $cfg['charset'] ?? 'utf8mb4'
    );
}

function pdo_options(): array
{
    return [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
}

function run_migrations(PDO $pdo, string $dir): void
{
    $all = glob($dir . '/*.sql') ?: [];
    // Garantir l'ordre : les CREATE avant les ALTER (les deux groupes triés alphabétiquement)
    $creates = array_values(array_filter($all, fn($f) => str_starts_with(basename($f), 'create_')));
    $alters  = array_values(array_filter($all, fn($f) => !str_starts_with(basename($f), 'create_')));
    sort($creates);
    sort($alters);
    $files = array_merge($creates, $alters);
    foreach ($files as $file) {
        $sql = trim((string) file_get_contents($file));
        if ($sql !== '') {
            $pdo->exec($sql);
        }
        // Enregistrement dans la table de suivi après chaque fichier
        try {
            $pdo->prepare('INSERT OR IGNORE INTO migrations (migration) VALUES (?)')
                ->execute([basename($file)]);
        } catch (Throwable) {
            // Fallback MySQL : syntaxe INSERT IGNORE
            try {
                $pdo->prepare('INSERT IGNORE INTO migrations (migration) VALUES (?)')
                    ->execute([basename($file)]);
            } catch (Throwable) {
                // Table de suivi pas encore disponible, on continue
            }
        }
    }
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

function init_jsondb_collections(string $migrationsDir, string $storageDir): void
{
    $all = glob($migrationsDir . '/*.json') ?: [];
    // Garantir l'ordre : les CREATE avant les ALTER
    $creates = array_values(array_filter($all, fn($f) => str_starts_with(basename($f), 'create_')));
    $alters  = array_values(array_filter($all, fn($f) => !str_starts_with(basename($f), 'create_')));
    sort($creates);
    sort($alters);
    $files = array_merge($creates, $alters);

    $dbFile = $storageDir . '/database.json';
    $db     = file_exists($dbFile)
        ? (json_decode((string) file_get_contents($dbFile), true) ?? [])
        : [];

    $trackingFile = $storageDir . '/migrations.json';
    $tracking     = file_exists($trackingFile)
        ? (json_decode((string) file_get_contents($trackingFile), true) ?? ['migrations' => []])
        : ['migrations' => []];
    $executed = array_column($tracking['migrations'], 'migration');

    foreach ($files as $file) {
        $schema = json_decode((string) file_get_contents($file), true);
        if (!array_key_exists($schema['table'], $db)) {
            $db[$schema['table']] = [];
        }
        $name = basename($file);
        if (!in_array($name, $executed, true)) {
            $tracking['migrations'][] = ['migration' => $name, 'executed_at' => date('Y-m-d H:i:s')];
        }
    }

    file_put_contents($dbFile,      json_encode($db,      JSON_PRETTY_PRINT));
    file_put_contents($trackingFile, json_encode($tracking, JSON_PRETTY_PRINT));
}

// ─── Installation logic ───────────────────────────────────────────────────────

$errors  = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // 1 — Collect and sanitise input
    $adminFirstName = trim($_POST['admin_first_name'] ?? '');
    $adminLastName  = trim($_POST['admin_last_name']  ?? '');
    $adminEmail     = trim($_POST['admin_email']      ?? '');
    $adminPassword =      $_POST['admin_password']  ?? '';
    $dbDriver      = trim($_POST['db_driver']       ?? '');
    $mysqlHost     = trim($_POST['mysql_host']      ?? '127.0.0.1');
    $mysqlPort     = (int) ($_POST['mysql_port']    ?? 3306);
    $mysqlDatabase = trim($_POST['mysql_database']  ?? '');
    $mysqlUsername = trim($_POST['mysql_username']  ?? '');
    $mysqlPassword =      $_POST['mysql_password']  ?? '';
    $runSeeds      = isset($_POST['run_seeds']);

    // 2 — Validate
    if ($adminFirstName === '') {
        $errors[] = 'First name is required.';
    }
    if ($adminLastName === '') {
        $errors[] = 'Last name is required.';
    }
    if (!filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'A valid administrator email is required.';
    }
    if (strlen($adminPassword) < 8) {
        $errors[] = 'Password must be at least 8 characters.';
    }
    if (!in_array($dbDriver, ['json', 'sqlite', 'mysql'], true)) {
        $errors[] = 'Please select a valid database driver.';
    }
    if ($dbDriver === 'mysql') {
        if ($mysqlHost === '')     $errors[] = 'MySQL host is required.';
        if ($mysqlDatabase === '') $errors[] = 'MySQL database name is required.';
        if ($mysqlUsername === '') $errors[] = 'MySQL username is required.';
    }

    // 3 — Run installation steps
    if (empty($errors)) {
        try {

            $mysqlCfg = [
                'host'     => $mysqlHost,
                'port'     => $mysqlPort,
                'database' => $mysqlDatabase,
                'username' => $mysqlUsername,
                'password' => $mysqlPassword,
                'charset'  => 'utf8mb4',
            ];

            // ── Step A: Provision database ────────────────────────────────

            if ($dbDriver === 'mysql') {
                // Connexion au serveur sans sélectionner de base de données
                $serverPdo = new PDO(
                    mysql_dsn_without_db($mysqlCfg),
                    $mysqlUsername,
                    $mysqlPassword,
                    pdo_options()
                );
                $serverPdo->exec(
                    "CREATE DATABASE IF NOT EXISTS `{$mysqlDatabase}` "
                    . "CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
                );
                unset($serverPdo);

                // Reconnexion à la base provisionnée
                $pdo = new PDO(
                    mysql_dsn_with_db($mysqlCfg),
                    $mysqlUsername,
                    $mysqlPassword,
                    pdo_options()
                );

                // ── Step B: Run migrations ────────────────────────────────

                run_migrations($pdo, BASE_PATH . '/database/migrations/mysql');

                // ── Step B2: Run seeds ────────────────────────────────────

                if ($runSeeds) {
                    run_seeds($pdo, BASE_PATH . '/database/seeds/mysql');
                }

                unset($pdo);

            } elseif ($dbDriver === 'sqlite') {
                $dbPath = storage_path('app/database.sqlite');
                $dbDir  = dirname($dbPath);
                if (!is_dir($dbDir)) {
                    mkdir($dbDir, 0755, recursive: true);
                }
                $pdo = new PDO('sqlite:' . $dbPath, options: pdo_options());

                // ── Step B: Run migrations ────────────────────────────────

                run_migrations($pdo, BASE_PATH . '/database/migrations/sqlite');

                // ── Step B2: Run seeds ────────────────────────────────────

                if ($runSeeds) {
                    run_seeds($pdo, BASE_PATH . '/database/seeds/sqlite');
                }

                unset($pdo);

            } elseif ($dbDriver === 'json') {
                // Initialise toutes les collections depuis les descripteurs de schéma jsondb
                // et enregistre les migrations dans storage/app/migrations.json.
                // Les seeds ne sont pas exécutées pour le pilote JSON.
                init_jsondb_collections(
                    BASE_PATH . '/database/migrations/jsondb',
                    storage_path('app')
                );
            }

            // ── Step C: Write config/database.local.php ───────────────────

            $localCfg = ['driver' => $dbDriver, 'connections' => []];

            if ($dbDriver === 'mysql') {
                $localCfg['connections']['mysql'] = [
                    'host'      => $mysqlHost,
                    'port'      => $mysqlPort,
                    'database'  => $mysqlDatabase,
                    'username'  => $mysqlUsername,
                    'password'  => $mysqlPassword,
                    'charset'   => 'utf8mb4',
                    'collation' => 'utf8mb4_unicode_ci',
                    'prefix'    => '',
                ];
            }

            file_put_contents(
                BASE_PATH . '/config/database.local.php',
                '<?php return ' . var_export($localCfg, true) . ';' . PHP_EOL
            );

            // ── Step D: Create admin user via the framework ───────────────

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

            // ── Step E: Lock installation ─────────────────────────────────

            file_put_contents(BASE_PATH . '/storage/installed.lock', (string) time());

            $success = true;
            header('Refresh: 2; URL=/');

        } catch (Throwable $e) {
            $errors[] = 'Installation failed: ' . $e->getMessage();

            // Annulation de l'état partiel pour permettre une nouvelle tentative
            @unlink(BASE_PATH . '/config/database.local.php');
            @unlink(BASE_PATH . '/storage/installed.lock');
        }
    }
}

// ─── Pre-fill from environment ────────────────────────────────────────────────

$defaultHost     = env('DB_HOST', '127.0.0.1');
$defaultPort     = env('DB_PORT', '3306');
$defaultDatabase = env('DB_DATABASE', 'kintai');
$defaultUsername = env('DB_USERNAME', 'root');

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kintai — Setup</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #f1f5f9; color: #334155; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 24px; }
        .card { background: #fff; width: 100%; max-width: 520px; border-radius: 12px; box-shadow: 0 4px 24px rgba(0,0,0,.08); overflow: hidden; }
        .card-header { background: #0f172a; padding: 28px 32px; }
        .card-header h1 { color: #f8fafc; font-size: 1.35rem; font-weight: 700; letter-spacing: -.01em; }
        .card-header p  { color: #94a3b8; font-size: .82rem; margin-top: 4px; }
        .card-body { padding: 28px 32px; }
        h2 { font-size: .78rem; text-transform: uppercase; letter-spacing: .07em; color: #64748b; margin: 24px 0 12px; border-bottom: 1px solid #e2e8f0; padding-bottom: 6px; }
        h2:first-child { margin-top: 0; }
        .form-row { margin-bottom: 14px; }
        label { display: block; font-size: .82rem; font-weight: 600; color: #475569; margin-bottom: 5px; }
        input[type=text], input[type=email], input[type=password], input[type=number] {
            width: 100%; padding: 9px 12px; border: 1px solid #cbd5e1; border-radius: 7px;
            font-size: .88rem; color: #0f172a; background: #f8fafc;
            transition: border-color .15s, box-shadow .15s;
        }
        input:focus { outline: none; border-color: #6366f1; box-shadow: 0 0 0 3px rgba(99,102,241,.15); }
        .driver-group { display: flex; gap: 10px; }
        .driver-btn { flex: 1; position: relative; }
        .driver-btn input[type=radio] { position: absolute; opacity: 0; width: 0; height: 0; }
        .driver-btn label {
            display: block; text-align: center; padding: 10px 8px; border: 2px solid #e2e8f0;
            border-radius: 8px; cursor: pointer; font-size: .82rem; font-weight: 600;
            color: #64748b; background: #f8fafc; transition: all .15s;
        }
        .driver-btn input:checked + label { border-color: #6366f1; color: #4f46e5; background: #eef2ff; }
        .alert { padding: 12px 14px; border-radius: 8px; font-size: .84rem; margin-bottom: 18px; }
        .alert-error   { background: #fef2f2; border: 1px solid #fca5a5; color: #b91c1c; }
        .alert-success { background: #f0fdf4; border: 1px solid #86efac; color: #15803d; }
        .alert ul { padding-left: 18px; }
        .alert ul li + li { margin-top: 3px; }
        .btn { width: 100%; padding: 11px; background: #4f46e5; color: #fff; font-size: .92rem; font-weight: 700; border: none; border-radius: 8px; cursor: pointer; margin-top: 20px; transition: background .15s; }
        .btn:hover { background: #4338ca; }
        .check-row { display: flex; align-items: center; gap: 9px; margin-bottom: 14px; }
        .check-row input[type=checkbox] { width: 16px; height: 16px; accent-color: #4f46e5; cursor: pointer; flex-shrink: 0; }
        .check-row label { margin: 0; font-size: .82rem; font-weight: 600; color: #475569; cursor: pointer; }
        .form-row--flex { display: flex; gap: 10px; }
        .form-row--flex > div { flex: 1; }
        .pw-hint { font-weight: 400; color: #94a3b8; }
        #mysql-section { display: none; }
    </style>
</head>
<body>
<div class="card">
    <div class="card-header">
        <h1>Kintai Setup</h1>
        <p>Configure your application in one step.</p>
    </div>
    <div class="card-body">

        <?php if (!empty($errors)): ?>
            <div class="alert alert-error">
                <ul>
                    <?php foreach ($errors as $err): ?>
                        <li><?= htmlspecialchars($err, ENT_QUOTES) ?></li>
                    <?php endforeach ?>
                </ul>
            </div>
        <?php endif ?>

        <?php if ($success): ?>
            <div class="alert alert-success">
                Installation complete! Redirecting&hellip;
            </div>
        <?php else: ?>

        <form method="POST" action="">

            <h2>Administrator account</h2>

            <div class="form-row form-row--flex">
                <div>
                    <label for="admin_first_name">First name</label>
                    <input type="text" id="admin_first_name" name="admin_first_name"
                           value="<?= htmlspecialchars($_POST['admin_first_name'] ?? '', ENT_QUOTES) ?>" required>
                </div>
                <div>
                    <label for="admin_last_name">Last name</label>
                    <input type="text" id="admin_last_name" name="admin_last_name"
                           value="<?= htmlspecialchars($_POST['admin_last_name'] ?? '', ENT_QUOTES) ?>" required>
                </div>
            </div>
            <div class="form-row">
                <label for="admin_email">Email</label>
                <input type="email" id="admin_email" name="admin_email"
                       value="<?= htmlspecialchars($_POST['admin_email'] ?? '', ENT_QUOTES) ?>" required>
            </div>
            <div class="form-row">
                <label for="admin_password">Password <small class="pw-hint">(min. 8 characters)</small></label>
                <input type="password" id="admin_password" name="admin_password" required>
            </div>

            <h2>Database driver</h2>

            <div class="driver-group">
                <?php foreach (['json' => 'JSON File', 'sqlite' => 'SQLite', 'mysql' => 'MySQL'] as $val => $lbl): ?>
                    <div class="driver-btn">
                        <input type="radio" id="driver_<?= $val ?>" name="db_driver" value="<?= $val ?>"
                               <?= (($_POST['db_driver'] ?? 'sqlite') === $val) ? 'checked' : '' ?>>
                        <label for="driver_<?= $val ?>"><?= $lbl ?></label>
                    </div>
                <?php endforeach ?>
            </div>

            <div id="mysql-section">
                <h2>MySQL connection</h2>
                <div class="form-row">
                    <label for="mysql_host">Host</label>
                    <input type="text" id="mysql_host" name="mysql_host"
                           value="<?= htmlspecialchars($_POST['mysql_host'] ?? $defaultHost, ENT_QUOTES) ?>">
                </div>
                <div class="form-row">
                    <label for="mysql_port">Port</label>
                    <input type="number" id="mysql_port" name="mysql_port"
                           value="<?= htmlspecialchars($_POST['mysql_port'] ?? $defaultPort, ENT_QUOTES) ?>">
                </div>
                <div class="form-row">
                    <label for="mysql_database">Database name</label>
                    <input type="text" id="mysql_database" name="mysql_database"
                           value="<?= htmlspecialchars($_POST['mysql_database'] ?? $defaultDatabase, ENT_QUOTES) ?>">
                </div>
                <div class="form-row">
                    <label for="mysql_username">Username</label>
                    <input type="text" id="mysql_username" name="mysql_username"
                           value="<?= htmlspecialchars($_POST['mysql_username'] ?? $defaultUsername, ENT_QUOTES) ?>">
                </div>
                <div class="form-row">
                    <label for="mysql_password">Password</label>
                    <input type="password" id="mysql_password" name="mysql_password">
                </div>
            </div>

            <h2>Options</h2>

            <div class="check-row" id="seeds-row">
                <input type="checkbox" id="run_seeds" name="run_seeds"
                       <?= isset($_POST['run_seeds']) ? 'checked' : '' ?>>
                <label for="run_seeds">Run database seeders</label>
            </div>

            <button type="submit" class="btn">Install Kintai</button>
        </form>

        <?php endif ?>

    </div>
</div>

<script>
(function () {
    const radios   = document.querySelectorAll('input[name="db_driver"]');
    const section  = document.getElementById('mysql-section');
    const seedsRow = document.getElementById('seeds-row');

    function sync() {
        const checked = document.querySelector('input[name="db_driver"]:checked');
        const driver  = checked ? checked.value : '';
        section.style.display  = (driver === 'mysql') ? '' : 'none';
        seedsRow.style.display = (driver === 'json')  ? 'none' : '';
    }

    radios.forEach(r => r.addEventListener('change', sync));
    sync();
})();
</script>
</body>
</html>
