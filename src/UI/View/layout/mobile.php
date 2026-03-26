<!DOCTYPE html>
<html lang="<?= \kintai\Core\Container::getInstance()->make(\kintai\Core\Services\TranslationService::class)->getLocale() ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#1f2937">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <title><?= htmlspecialchars($title ?? 'Kintai') ?> — Kintai</title>
    <link rel="stylesheet" href="<?= $BASE_URL ?>/assets/css/app.css">
    <link rel="stylesheet" href="<?= $BASE_URL ?>/assets/css/mobile.css">
</head>

<body class="mob-body">
<?php
use kintai\Core\Container;

$uri   = '/' . trim(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), '/');
$base  = rtrim($BASE_URL, '/');
$path  = $base !== '' && str_starts_with($uri, $base)
    ? substr($uri, strlen($base))
    : $uri;
$path  = '/' . trim($path, '/') ?: '/';

$isOwner   = !empty($auth_user['is_admin']);
$isManager = $isOwner || isset($managed_store_ids);
$viewMode       = $view_mode ?? 'admin';
$showAdminMenu  = $isManager && $viewMode === 'admin';

// Détection active pour la barre du bas
$activeTab = 'home';
if ($showAdminMenu) {
    if (str_starts_with($path, '/admin/shifts') && !str_contains($path, 'shift-type')) $activeTab = 'shifts';
    elseif (str_starts_with($path, '/admin/users'))       $activeTab = 'team';
    elseif (str_starts_with($path, '/admin/timeoff') || str_starts_with($path, '/admin/swap-requests')) $activeTab = 'requests';
    elseif ($path === '/' || $path === '')                $activeTab = 'home';
} else {
    if (str_starts_with($path, '/employee/shifts')) $activeTab = 'shifts';
    elseif (str_starts_with($path, '/employee/timeoff'))  $activeTab = 'timeoff';
    elseif (str_starts_with($path, '/employee/swaps'))    $activeTab = 'swaps';
    elseif ($path === '/employee' || $path === '/')        $activeTab = 'home';
}
?>

<div class="mob-layout">

    <!-- ═══════════════════════════════════════════ HEADER ═══════════════════════════════════════════ -->
    <header class="mob-header">
        <div class="mob-header__left">
            <button class="mob-hamburger" id="mobMenuBtn" onclick="mobDrawerOpen()" aria-label="Menu">
                <span></span><span></span><span></span>
            </button>
            <span class="mob-header__brand">Kintai</span>
        </div>
        <div class="mob-header__title"><?= htmlspecialchars($title ?? '') ?></div>
        <div class="mob-header__right">
            <!-- Switcher langue compact -->
            <?php $_locale = \kintai\Core\Container::getInstance()->make(\kintai\Core\Services\TranslationService::class)->getLocale(); ?>
            <div class="mob-lang">
                <a href="<?= $BASE_URL ?>/lang/fr" class="mob-lang__item<?= $_locale === 'fr' ? ' active' : '' ?>">FR</a>
                <a href="<?= $BASE_URL ?>/lang/en" class="mob-lang__item<?= $_locale === 'en' ? ' active' : '' ?>">EN</a>
                <a href="<?= $BASE_URL ?>/lang/ja" class="mob-lang__item<?= $_locale === 'ja' ? ' active' : '' ?>">JA</a>
            </div>
        </div>
    </header>

    <!-- ═════════════════════════════════════════ DRAWER (slide-in) ══════════════════════════════════ -->
    <div class="mob-drawer-overlay" id="mobDrawerOverlay" onclick="mobDrawerClose()"></div>
    <aside class="mob-drawer" id="mobDrawer">
        <div class="mob-drawer__header">
            <?php
            $displayName = htmlspecialchars(
                $auth_user['display_name']
                    ?? trim(($auth_user['first_name'] ?? '') . ' ' . ($auth_user['last_name'] ?? ''))
                    ?: ($auth_user['email'] ?? __('user'))
            );
            $roleLabel = $isOwner ? __('role_owner') : ($isManager ? __('role_manager') : __('role_employee'));
            ?>
            <div class="mob-drawer__avatar"><?= mb_strtoupper(mb_substr($displayName, 0, 1)) ?></div>
            <div class="mob-drawer__user">
                <strong><?= $displayName ?></strong>
                <small><?= $roleLabel ?></small>
            </div>
        </div>

        <nav class="mob-drawer__nav">
            <?php if ($showAdminMenu && $isOwner): ?>
                <div class="mob-drawer__section"><?= __('dashboard') ?></div>
                <a href="<?= $BASE_URL ?>/" class="mob-drawer__link<?= ($path === '/' || $path === '') ? ' active' : '' ?>">🏠 <?= __('dashboard') ?></a>

                <div class="mob-drawer__section"><?= __('planning') ?></div>
                <a href="<?= $BASE_URL ?>/admin/shifts/timeline" class="mob-drawer__link<?= (str_starts_with($path, '/admin/shifts') && !str_contains($path, 'shift-type')) ? ' active' : '' ?>">📅 <?= __('shifts') ?></a>
                <a href="<?= $BASE_URL ?>/admin/shifts/calendar" class="mob-drawer__link<?= str_starts_with($path, '/admin/shifts/calendar') ? ' active' : '' ?>">🗓 <?= __('calendar') ?></a>
                <a href="<?= $BASE_URL ?>/admin/shift-types" class="mob-drawer__link<?= str_starts_with($path, '/admin/shift-types') ? ' active' : '' ?>">🏷 <?= __('shift_types') ?></a>

                <div class="mob-drawer__section"><?= __('hr') ?></div>
                <a href="<?= $BASE_URL ?>/admin/users" class="mob-drawer__link<?= str_starts_with($path, '/admin/users') ? ' active' : '' ?>">👥 <?= __('users') ?></a>
                <a href="<?= $BASE_URL ?>/admin/stores" class="mob-drawer__link<?= str_starts_with($path, '/admin/stores') ? ' active' : '' ?>">🏪 <?= __('stores') ?></a>

                <div class="mob-drawer__section"><?= __('requests') ?></div>
                <a href="<?= $BASE_URL ?>/admin/timeoff" class="mob-drawer__link<?= str_starts_with($path, '/admin/timeoff') ? ' active' : '' ?>">🌴 <?= __('timeoff') ?></a>
                <a href="<?= $BASE_URL ?>/admin/swap-requests" class="mob-drawer__link<?= str_starts_with($path, '/admin/swap-requests') ? ' active' : '' ?>">🔄 <?= __('swaps') ?></a>

                <div class="mob-drawer__section"><?= __('system') ?></div>
                <a href="<?= $BASE_URL ?>/admin/audit-log" class="mob-drawer__link<?= str_starts_with($path, '/admin/audit-log') ? ' active' : '' ?>">📋 <?= __('audit_log') ?></a>
                <a href="<?= $BASE_URL ?>/admin/feedbacks" class="mob-drawer__link<?= str_starts_with($path, '/admin/feedbacks') ? ' active' : '' ?>">💬 <?= __('feedbacks') ?></a>

            <?php elseif ($showAdminMenu): ?>
                <div class="mob-drawer__section"><?= __('planning') ?></div>
                <a href="<?= $BASE_URL ?>/admin/shifts/timeline" class="mob-drawer__link<?= (str_starts_with($path, '/admin/shifts') && !str_contains($path, 'shift-type')) ? ' active' : '' ?>">📅 <?= __('shifts') ?></a>
                <a href="<?= $BASE_URL ?>/admin/shift-types" class="mob-drawer__link<?= str_starts_with($path, '/admin/shift-types') ? ' active' : '' ?>">🏷 <?= __('shift_types') ?></a>

                <div class="mob-drawer__section"><?= __('hr') ?></div>
                <a href="<?= $BASE_URL ?>/admin/users" class="mob-drawer__link<?= str_starts_with($path, '/admin/users') ? ' active' : '' ?>">👥 <?= __('staff') ?></a>
                <a href="<?= $BASE_URL ?>/admin/stores" class="mob-drawer__link<?= str_starts_with($path, '/admin/stores') ? ' active' : '' ?>">🏪 <?= __('stores') ?></a>

                <div class="mob-drawer__section"><?= __('requests') ?></div>
                <a href="<?= $BASE_URL ?>/admin/timeoff" class="mob-drawer__link<?= str_starts_with($path, '/admin/timeoff') ? ' active' : '' ?>">🌴 <?= __('timeoff') ?></a>
                <a href="<?= $BASE_URL ?>/admin/swap-requests" class="mob-drawer__link<?= str_starts_with($path, '/admin/swap-requests') ? ' active' : '' ?>">🔄 <?= __('swaps') ?></a>

            <?php else: ?>
                <div class="mob-drawer__section"><?= __('my_account') ?></div>
                <a href="<?= $BASE_URL ?>/employee" class="mob-drawer__link<?= $path === '/employee' ? ' active' : '' ?>">🏠 <?= __('dashboard') ?></a>
                <a href="<?= $BASE_URL ?>/employee/shifts/day" class="mob-drawer__link<?= str_starts_with($path, '/employee/shifts') ? ' active' : '' ?>">📅 <?= __('my_planning') ?></a>
                <a href="<?= $BASE_URL ?>/employee/shifts/calendar" class="mob-drawer__link<?= str_starts_with($path, '/employee/shifts/calendar') ? ' active' : '' ?>">🗓 <?= __('calendar') ?></a>

                <div class="mob-drawer__section"><?= __('requests') ?></div>
                <a href="<?= $BASE_URL ?>/employee/timeoff" class="mob-drawer__link<?= str_starts_with($path, '/employee/timeoff') ? ' active' : '' ?>">🌴 <?= __('my_timeoff') ?></a>
                <a href="<?= $BASE_URL ?>/employee/swaps" class="mob-drawer__link<?= str_starts_with($path, '/employee/swaps') ? ' active' : '' ?>">🔄 <?= __('swaps') ?></a>
            <?php endif; ?>
        </nav>

        <!-- Actions bas du drawer -->
        <div class="mob-drawer__footer">
            <a href="<?= $BASE_URL ?>/profile" class="mob-drawer__link">⚙️ <?= __('profile') ?></a>

            <?php if ($auth_is_manager ?? $isManager): ?>
                <form method="POST" action="<?= $BASE_URL ?>/switch-view" class="m-0">
                    <button type="submit" class="mob-drawer__link mob-drawer__link--btn">
                        🔀 <?= $viewMode === 'admin' ? __('view_employee') : __('view_admin') ?>
                    </button>
                </form>
            <?php endif; ?>

            <!-- Passer en vue bureau -->
            <form method="POST" action="<?= $BASE_URL ?>/switch-device" class="m-0">
                <input type="hidden" name="device_view" value="desktop">
                <button type="submit" class="mob-drawer__link mob-drawer__link--btn">🖥 <?= __('switch_to_desktop') ?></button>
            </form>

            <form method="POST" action="<?= $BASE_URL ?>/logout" class="m-0">
                <button type="submit" class="mob-drawer__link mob-drawer__link--btn mob-drawer__link--danger">
                    🚪 <?= __('logout') ?>
                </button>
            </form>
        </div>
    </aside>

    <!-- ════════════════════════════════════════════ MAIN ════════════════════════════════════════════ -->
    <main class="mob-main">
        <?= $content ?>
    </main>

    <!-- ══════════════════════════════════════ BOTTOM NAV ════════════════════════════════════════════ -->
    <nav class="mob-bottomnav" aria-label="Navigation principale">
        <?php if ($showAdminMenu): ?>
            <!-- Barre admin/manager -->
            <a href="<?= $BASE_URL ?>/" class="mob-tab<?= $activeTab === 'home' ? ' mob-tab--active' : '' ?>">
                <span class="mob-tab__icon">🏠</span>
                <span class="mob-tab__label"><?= __('home') ?></span>
            </a>
            <a href="<?= $BASE_URL ?>/admin/shifts/timeline" class="mob-tab<?= $activeTab === 'shifts' ? ' mob-tab--active' : '' ?>">
                <span class="mob-tab__icon">📅</span>
                <span class="mob-tab__label"><?= __('shifts') ?></span>
            </a>
            <a href="<?= $BASE_URL ?>/admin/users" class="mob-tab<?= $activeTab === 'team' ? ' mob-tab--active' : '' ?>">
                <span class="mob-tab__icon">👥</span>
                <span class="mob-tab__label"><?= __('team') ?></span>
            </a>
            <a href="<?= $BASE_URL ?>/admin/timeoff" class="mob-tab<?= $activeTab === 'requests' ? ' mob-tab--active' : '' ?>">
                <span class="mob-tab__icon">📋</span>
                <span class="mob-tab__label"><?= __('requests') ?></span>
            </a>
        <?php else: ?>
            <!-- Barre employé -->
            <a href="<?= $BASE_URL ?>/employee" class="mob-tab<?= $activeTab === 'home' ? ' mob-tab--active' : '' ?>">
                <span class="mob-tab__icon">🏠</span>
                <span class="mob-tab__label"><?= __('home') ?></span>
            </a>
            <a href="<?= $BASE_URL ?>/employee/shifts/day" class="mob-tab<?= $activeTab === 'shifts' ? ' mob-tab--active' : '' ?>">
                <span class="mob-tab__icon">📅</span>
                <span class="mob-tab__label"><?= __('planning') ?></span>
            </a>
            <a href="<?= $BASE_URL ?>/employee/timeoff" class="mob-tab<?= $activeTab === 'timeoff' ? ' mob-tab--active' : '' ?>">
                <span class="mob-tab__icon">🌴</span>
                <span class="mob-tab__label"><?= __('timeoff') ?></span>
            </a>
            <a href="<?= $BASE_URL ?>/employee/swaps" class="mob-tab<?= $activeTab === 'swaps' ? ' mob-tab--active' : '' ?>">
                <span class="mob-tab__icon">🔄</span>
                <span class="mob-tab__label"><?= __('swaps') ?></span>
            </a>
        <?php endif; ?>
    </nav>

</div><!-- /.mob-layout -->

<script src="<?= $BASE_URL ?>/assets/js/app.js"></script>

<script>
// Drawer mobile
function mobDrawerOpen() {
    document.getElementById('mobDrawer').classList.add('open');
    document.getElementById('mobDrawerOverlay').classList.add('open');
    document.body.style.overflow = 'hidden';
}
function mobDrawerClose() {
    document.getElementById('mobDrawer').classList.remove('open');
    document.getElementById('mobDrawerOverlay').classList.remove('open');
    document.body.style.overflow = '';
}
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') mobDrawerClose();
});
</script>

<?php if (!$showAdminMenu): ?>
    <?php include __DIR__ . '/partials/feedback-modal.php'; ?>
<?php endif; ?>

</body>
</html>
