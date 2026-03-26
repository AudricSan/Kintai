<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title ?? 'Kintai') ?> — Kintai</title>
    <link rel="stylesheet" href="<?= $BASE_URL ?>/assets/css/app.css">
</head>
<body class="guest-layout">
    <div class="guest-container">
        <div class="guest-brand">
            <h1>Kintai</h1>
            <p>Shift Management</p>
        </div>
        <div class="guest-card">
            <?= $content ?>
        </div>
    </div>
</body>
</html>
