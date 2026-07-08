<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= htmlspecialchars(csrf_token(), ENT_QUOTES) ?>">
    <title><?= htmlspecialchars($title ?? 'Kintai') ?> — Kintai</title>
    <link rel="stylesheet" href="<?= $BASE_URL ?>/assets/css/app.css">
</head>
<body class="guest-layout">
    <div class="guest-container">
        <div class="guest-brand">
            <h1>Kintai</h1>
            <?php if (!empty($app_subtitle)): ?>
                <p class="guest-brand__company"><?= htmlspecialchars($app_subtitle, ENT_QUOTES) ?></p>
            <?php else: ?>
                <p>Shift Management</p>
            <?php endif; ?>
        </div>
        <?php if (!empty($app_login_notice)): ?>
            <div class="guest-notice"><?= htmlspecialchars($app_login_notice, ENT_QUOTES) ?></div>
        <?php endif; ?>
        <div class="guest-card">
            <?= $content ?>
        </div>
    </div>
</body>
</html>
