<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= __('error_405_title') ?></title>
    <link rel="stylesheet" href="<?= ($BASE_URL ?? '') ?>/assets/css/app.css">
</head>
<body class="error-body">
    <div class="error-page">
        <div class="error-code">405</div>
        <p class="error-message"><?= htmlspecialchars($message ?? __('error_405_message')) ?></p>
        <a href="/" class="error-link">← <?= __('back_to_dashboard') ?></a>
    </div>
</body>
</html>
