<!DOCTYPE html>
<html lang="en" data-mascot="<?= htmlspecialchars(mascot_active(), ENT_QUOTES) ?>" style="<?= htmlspecialchars($app_theme_color_style ?? '', ENT_QUOTES) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= htmlspecialchars(csrf_token(), ENT_QUOTES) ?>">
    <title><?= htmlspecialchars($title ?? 'Kintai') ?> — Kintai</title>
    <link rel="icon" href="<?= $BASE_URL ?>/assets/img/favicon.ico" sizes="any">
    <link rel="icon" type="image/png" sizes="32x32" href="<?= $BASE_URL ?>/assets/img/favicon-32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="<?= $BASE_URL ?>/assets/img/favicon-16.png">
    <link rel="apple-touch-icon" sizes="180x180" href="<?= $BASE_URL ?>/assets/img/apple-touch-icon.png">
    <link rel="stylesheet" href="<?= $BASE_URL ?>/assets/css/app.css?v=<?= asset_version() ?>">
</head>
<body class="guest-layout">
    <div class="guest-main">
        <div class="guest-container">
            <div class="guest-brand">
                <img src="<?= $BASE_URL ?>/assets/img/<?= mascot_path('login') ?>" alt="<?= __('mascot_alt') ?>" class="guest-brand__mascot">
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
    </div>

    <?php include __DIR__ . '/partials/_footer.php'; ?>
</body>
</html>
