<?php
$_bnActive = '';
if ($showAdminMenu) {
    if (str_starts_with($path, '/admin/shifts') && !str_contains($path, 'shift-type')) $_bnActive = 'shifts';
    elseif (str_starts_with($path, '/admin/users') || str_starts_with($path, '/admin/stores')) $_bnActive = 'team';
    elseif (str_starts_with($path, '/admin/timeoff')) $_bnActive = 'requests';
    elseif (str_starts_with($path, '/admin/messages')) $_bnActive = 'messages';
    elseif (str_starts_with($path, '/admin/swap-requests')) $_bnActive = 'swaps';
    elseif (str_starts_with($path, '/admin/open-shifts')) $_bnActive = 'open_shifts';
    elseif (str_starts_with($path, '/admin/timeclocks')) $_bnActive = 'timeclocks';
    elseif (str_contains($path, '/daily-reports')) $_bnActive = 'daily_reports';
    elseif ($path === '/' || $path === '') $_bnActive = 'home';
} else {
    if (str_starts_with($path, '/employee/shifts')) $_bnActive = 'my_planning';
    elseif (str_starts_with($path, '/employee/timeclock')) $_bnActive = 'timeclock';
    elseif (str_starts_with($path, '/employee/timeoff')) $_bnActive = 'my_timeoff';
    elseif (str_starts_with($path, '/employee/swaps')) $_bnActive = 'swaps';
    elseif (str_starts_with($path, '/employee/messages')) $_bnActive = 'messages';
    elseif (str_starts_with($path, '/employee/open-shifts')) $_bnActive = 'open_shifts';
    elseif (str_starts_with($path, '/profile')) $_bnActive = 'my_profile';
    elseif ($path === '/employee' || $path === '/') $_bnActive = 'home';
}

// href en closures : évite d'appeler route_url() sur une route dont le bundle
// est désactivé avant le filtrage par $feat() ci-dessous (route_url() lève une
// exception si la route n'est pas enregistrée).
$_bnAdminPool = [
        'shifts'        => ['icon' => 'calendar', 'label' => __('shifts'),        'href' => fn() => route_url('admin.shifts.timeline'), 'feat' => 'shifts'],
        'team'          => ['icon' => 'users',     'label' => __('team'),          'href' => fn() => route_url('admin.users'),           'feat' => null],
        'requests'      => ['icon' => 'clipboard', 'label' => __('requests'),      'href' => fn() => route_url('admin.timeoff'),         'feat' => 'timeoff'],
        'messages'      => ['icon' => 'message',   'label' => __('messages'),      'href' => fn() => route_url('admin.messages'),        'feat' => 'messages'],
        'swaps'         => ['icon' => 'arrows',    'label' => __('swaps'),         'href' => fn() => route_url('admin.swap_requests'),   'feat' => 'swaps'],
        'timeclocks'    => ['icon' => 'clock',     'label' => __('timeclocks'),    'href' => fn() => route_url('admin.timeclocks'),      'feat' => 'timeclock'],
        'daily_reports' => ['icon' => 'chart',     'label' => __('daily_reports'), 'href' => fn() => route_url('admin.daily_reports.all'),  'feat' => 'daily_reports'],
];
$_bnEmployeePool = [
    'my_planning' => ['icon' => 'calendar', 'label' => __('my_planning'),  'href' => fn() => route_url('employee.shifts.day'),  'feat' => 'shifts'],
    'timeclock'   => ['icon' => 'clock',    'label' => __('timeclock'),    'href' => fn() => route_url('employee.timeclock'),   'feat' => 'timeclock'],
    'my_timeoff'  => ['icon' => 'leaf',     'label' => __('my_timeoff'),   'href' => fn() => route_url('employee.timeoff'),     'feat' => 'timeoff'],
    'messages'    => ['icon' => 'message',  'label' => __('messages'),     'href' => fn() => route_url('employee.messages'),    'feat' => 'messages'],
    'swaps'       => ['icon' => 'arrows',   'label' => __('swaps'),        'href' => fn() => route_url('employee.swaps'),       'feat' => 'swaps'],
    'open_shifts' => ['icon' => 'plus',     'label' => __('open_shifts'),  'href' => fn() => route_url('employee.open_shifts'), 'feat' => 'open_shifts'],
    'my_profile'  => ['icon' => 'person',   'label' => __('my_profile'),   'href' => fn() => route_url('profile'),              'feat' => null],
];

if ($showAdminMenu) {
    $_bnPool    = $_bnAdminPool;
    $_bnDefault = ['shifts', 'team', 'requests'];
    $_bnHomeHref = route_url('home');
} else {
    $_bnPool    = $_bnEmployeePool;
    $_bnDefault = ['my_planning', 'timeclock', 'my_timeoff'];
    $_bnHomeHref = route_url('employee.dashboard');
}

$_bnSavedItems = (array)($user_bottom_nav_items ?? []);
$_bnKeys = !empty($_bnSavedItems) ? $_bnSavedItems : $_bnDefault;
$_bnRendered = [];
foreach ($_bnKeys as $_bnKey) {
    if (!isset($_bnPool[$_bnKey])) continue;
    $_bnItem = $_bnPool[$_bnKey];
    $f = $_bnItem['feat'] ?? null;
    if ($f !== null && !$feat($f)) continue;
    $_bnRendered[$_bnKey] = $_bnItem;
}
if (count($_bnRendered) < 2) {
    foreach ($_bnDefault as $_bnKey) {
        if (count($_bnRendered) >= 2) break;
        if (!isset($_bnPool[$_bnKey]) || isset($_bnRendered[$_bnKey])) continue;
        $_bnItem = $_bnPool[$_bnKey];
        $f = $_bnItem['feat'] ?? null;
        if ($f !== null && !$feat($f)) continue;
        $_bnRendered[$_bnKey] = $_bnItem;
    }
}
?>
<nav class="bottom-nav" aria-label="Navigation principale">
    <a href="<?= $_bnHomeHref ?>" class="nav-tab<?= $_bnActive === 'home' ? ' nav-tab--active' : '' ?>">
        <span class="nav-tab__icon"><?= $svgIcon('home') ?></span>
        <span class="nav-tab__label"><?= __('home') ?></span>
    </a>
    <?php foreach ($_bnRendered as $_bnKey => $_bnItem): ?>
    <a href="<?= htmlspecialchars($_bnItem['href']()) ?>" class="nav-tab<?= $_bnActive === $_bnKey ? ' nav-tab--active' : '' ?>">
        <span class="nav-tab__icon"><?= $svgIcon($_bnItem['icon']) ?></span>
        <span class="nav-tab__label"><?= htmlspecialchars($_bnItem['label']) ?></span>
    </a>
    <?php endforeach; ?>
</nav>
