<?php
$onShifts         = str_starts_with($path, '/admin/shifts') && !str_contains($path, 'shift-type');
$onShiftsTimeline = str_starts_with($path, '/admin/shifts/timeline');
$onCalendar       = str_starts_with($path, '/admin/shifts/calendar');
$ico              = fn(string $k): string => '<span class="topbar-nav-group__link-icon">' . $svgIcon($k) . '</span>';
?>
<header class="topbar">
    <a href="<?= route_url('home') ?>" class="topbar-brand"><span class="topbar-brand__dot"></span>Kintai<?php if (!empty($app_subtitle)): ?><span class="topbar-brand__subtitle"><?= htmlspecialchars($app_subtitle, ENT_QUOTES) ?></span><?php endif; ?></a>

    <button type="button" class="topbar-nav-toggle" id="topbarNavToggle" aria-label="Menu" aria-expanded="false">☰</button>

    <nav class="topbar-nav" id="topbarNav" aria-label="Navigation principale">
        <?php if ($showAdminMenu && $isOwner): ?>
            <?php
            $navHide = fn(string $k): bool => in_array($k, (array)($user_nav_hidden ?? []), true);
            $_defSec = ['planning', 'hr', 'requests', 'statistics', 'system'];
            $_rawOrd = (array)($user_nav_section_order ?? []);
            $_secOrd = array_values(array_unique(array_merge(
                array_intersect($_rawOrd, $_defSec), $_defSec
            )));
            ?>
            <a href="<?= route_url('home') ?>" class="topbar-nav-link<?= ($path === '/' || $path === '') ? ' topbar-nav-link--active' : '' ?>"><?= __('dashboard') ?></a>

            <?php foreach ($_secOrd as $_sec): switch ($_sec):
                case 'planning': ?>
                    <?php if (!$navHide('shifts') || !$navHide('calendar') || !$navHide('shift_types') || ($feat('timeclock') && !$navHide('timeclocks'))): ?>
                    <div class="topbar-nav-group<?= ($onShifts || $onCalendar || str_starts_with($path, '/admin/shift-types') || str_starts_with($path, '/admin/timeclocks')) ? ' topbar-nav-group--active' : '' ?>">
                        <button type="button" class="topbar-nav-group__trigger"><?= __('planning') ?> <span class="topbar-nav-group__caret">▾</span></button>
                        <div class="topbar-nav-group__panel">
                            <?php if (!$navHide('shifts')): ?>
                            <a href="<?= route_url('admin.shifts.timeline') ?>" class="topbar-nav-group__link<?= $onShifts ? ' topbar-nav-group__link--active' : '' ?>"><?= $ico('calendar') ?><?= __('shifts') ?></a>
                            <?php endif; ?>
                            <?php if (!$navHide('calendar')): ?>
                            <a href="<?= route_url('admin.shifts.calendar') ?>" class="topbar-nav-group__link<?= $onCalendar ? ' topbar-nav-group__link--active' : '' ?>"><?= $ico('calendar') ?><?= __('calendar') ?></a>
                            <?php endif; ?>
                            <?php if (!$navHide('shift_types')): ?>
                            <a href="<?= route_url('admin.shift_types') ?>" class="topbar-nav-group__link<?= str_starts_with($path, '/admin/shift-types') ? ' topbar-nav-group__link--active' : '' ?>"><?= $ico('tag') ?><?= __('shift_types') ?></a>
                            <?php endif; ?>
                            <?php if ($feat('timeclock') && !$navHide('timeclocks')): ?>
                            <a href="<?= route_url('admin.timeclocks') ?>" class="topbar-nav-group__link<?= str_starts_with($path, '/admin/timeclocks') ? ' topbar-nav-group__link--active' : '' ?>"><?= $ico('clock') ?><?= __('timeclocks') ?></a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                <?php break; case 'hr': ?>
                    <?php if (!$navHide('users') || !$navHide('stores')): ?>
                    <div class="topbar-nav-group<?= (str_starts_with($path, '/admin/users') || str_starts_with($path, '/admin/stores')) ? ' topbar-nav-group--active' : '' ?>">
                        <button type="button" class="topbar-nav-group__trigger"><?= __('hr') ?> <span class="topbar-nav-group__caret">▾</span></button>
                        <div class="topbar-nav-group__panel">
                            <?php if (!$navHide('users')): ?>
                            <a href="<?= route_url('admin.users') ?>" class="topbar-nav-group__link<?= str_starts_with($path, '/admin/users') ? ' topbar-nav-group__link--active' : '' ?>"><?= $ico('users') ?><?= __('users') ?></a>
                            <?php endif; ?>
                            <?php if (!$navHide('stores')): ?>
                            <a href="<?= route_url('admin.stores') ?>" class="topbar-nav-group__link<?= str_starts_with($path, '/admin/stores') ? ' topbar-nav-group__link--active' : '' ?>"><?= $ico('store') ?><?= __('stores') ?></a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                <?php break; case 'requests': ?>
                    <?php if (($feat('timeoff') && !$navHide('timeoff')) || ($feat('swaps') && !$navHide('swaps')) || ($feat('open_shifts') && !$navHide('open_shifts')) || ($feat('messages') && !$navHide('messages'))): ?>
                    <div class="topbar-nav-group<?= (str_starts_with($path, '/admin/timeoff') || str_starts_with($path, '/admin/swap-requests') || str_starts_with($path, '/admin/open-shifts') || str_starts_with($path, '/admin/messages')) ? ' topbar-nav-group--active' : '' ?>">
                        <button type="button" class="topbar-nav-group__trigger"><?= __('requests') ?> <span class="topbar-nav-group__caret">▾</span></button>
                        <div class="topbar-nav-group__panel">
                            <?php if ($feat('timeoff') && !$navHide('timeoff')): ?>
                            <a href="<?= route_url('admin.timeoff') ?>" class="topbar-nav-group__link<?= str_starts_with($path, '/admin/timeoff') ? ' topbar-nav-group__link--active' : '' ?>"><?= $ico('leaf') ?><?= __('timeoff') ?></a>
                            <?php endif; ?>
                            <?php if ($feat('swaps') && !$navHide('swaps')): ?>
                            <a href="<?= route_url('admin.swap_requests') ?>" class="topbar-nav-group__link<?= str_starts_with($path, '/admin/swap-requests') ? ' topbar-nav-group__link--active' : '' ?>"><?= $ico('arrows') ?><?= __('swaps') ?></a>
                            <?php endif; ?>
                            <?php if ($feat('open_shifts') && !$navHide('open_shifts')): ?>
                            <a href="<?= route_url('admin.open_shifts') ?>" class="topbar-nav-group__link<?= str_starts_with($path, '/admin/open-shifts') ? ' topbar-nav-group__link--active' : '' ?>"><?= $ico('plus') ?><?= __('open_shifts') ?></a>
                            <?php endif; ?>
                            <?php if ($feat('messages') && !$navHide('messages')): ?>
                            <a href="<?= route_url('admin.messages') ?>" class="topbar-nav-group__link<?= str_starts_with($path, '/admin/messages') ? ' topbar-nav-group__link--active' : '' ?>">
                                <?= $ico('message') ?><?= __('messages') ?>
                                <?php if (($unread_messages_count ?? 0) > 0): ?><span class="nav-badge"><?= (int) $unread_messages_count ?></span><?php endif; ?>
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                <?php break; case 'statistics': ?>
                    <?php if ((bundle_enabled('hiring-report') && !$navHide('hiring_report')) || ($feat('daily_reports') && !$navHide('daily_reports')) || (bundle_enabled('resignation-report') && !$navHide('resignation_report')) || (bundle_enabled('salary-report') && !$navHide('salary_report')) || ($feat('photos') && !$navHide('photos'))): ?>
                    <div class="topbar-nav-group<?= (str_contains($path, '/reports/') || str_contains($path, '/daily-reports') || str_contains($path, '/admin/photos')) ? ' topbar-nav-group--active' : '' ?>">
                        <button type="button" class="topbar-nav-group__trigger"><?= __('reports') ?> <span class="topbar-nav-group__caret">▾</span></button>
                        <div class="topbar-nav-group__panel">
                            <?php if (bundle_enabled('hiring-report') && !$navHide('hiring_report')): ?>
                            <a href="<?= route_url('admin.reports.hiring') ?>" class="topbar-nav-group__link<?= str_contains($path, '/reports/hiring') ? ' topbar-nav-group__link--active' : '' ?>"><?= $ico('person') ?><?= __('hiring_reports') ?></a>
                            <?php endif; ?>
                            <?php if ($feat('daily_reports') && !$navHide('daily_reports')): ?>
                            <a href="<?= route_url('admin.daily_reports.all') ?>" class="topbar-nav-group__link<?= str_contains($path, '/daily-reports') ? ' topbar-nav-group__link--active' : '' ?>"><?= $ico('chart') ?><?= __('daily_reports') ?></a>
                            <?php endif; ?>
                            <?php if (bundle_enabled('resignation-report') && !$navHide('resignation_report')): ?>
                            <a href="<?= route_url('admin.reports.resignation') ?>" class="topbar-nav-group__link<?= str_contains($path, '/reports/resignation') ? ' topbar-nav-group__link--active' : '' ?>"><?= $ico('exit') ?><?= __('resignation_report') ?></a>
                            <?php endif; ?>
                            <?php if (bundle_enabled('salary-report') && !$navHide('salary_report')): ?>
                            <a href="<?= route_url('admin.reports.salary') ?>" class="topbar-nav-group__link<?= str_contains($path, '/reports/salary') ? ' topbar-nav-group__link--active' : '' ?>"><?= $ico('money') ?><?= __('salary_report') ?></a>
                            <?php endif; ?>
                            <?php if ($feat('photos') && !$navHide('photos')): ?>
                            <a href="<?= route_url('admin.photos.index') ?>" class="topbar-nav-group__link<?= str_contains($path, '/admin/photos') ? ' topbar-nav-group__link--active' : '' ?>"><?= $ico('camera') ?><?= __('photos_report') ?></a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                <?php break; case 'system': ?>
                    <div class="topbar-nav-group<?= (str_starts_with($path, '/admin/activity') || str_starts_with($path, '/admin/owner-settings') || str_starts_with($path, '/admin/feedbacks') || str_starts_with($path, '/admin/backup') || str_starts_with($path, '/admin/update') || str_starts_with($path, '/admin/languages') || str_starts_with($path, '/admin/bundles')) ? ' topbar-nav-group--active' : '' ?>">
                        <button type="button" class="topbar-nav-group__trigger"><?= __('system') ?> <span class="topbar-nav-group__caret">▾</span></button>
                        <div class="topbar-nav-group__panel">
                            <?php if (!$navHide('audit_log')): ?>
                            <a href="<?= route_url('admin.activity') ?>" class="topbar-nav-group__link<?= str_starts_with($path, '/admin/activity') ? ' topbar-nav-group__link--active' : '' ?>"><?= $ico('history') ?><?= __('activity_log') ?></a>
                            <?php endif; ?>
                            <?php if ($isOwner): ?>
                            <a href="<?= route_url('admin.owner_settings') ?>" class="topbar-nav-group__link<?= (str_starts_with($path, '/admin/owner-settings') || str_starts_with($path, '/admin/feedbacks') || str_starts_with($path, '/admin/backup') || str_starts_with($path, '/admin/update') || str_starts_with($path, '/admin/languages') || str_starts_with($path, '/admin/bundles')) ? ' topbar-nav-group__link--active' : '' ?>"><?= $ico('gear') ?><?= __('settings') ?></a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php break; endswitch; endforeach; ?>

        <?php elseif ($showAdminMenu): ?>
            <?php
            $navHide = fn(string $k): bool => in_array($k, (array)($user_nav_hidden ?? []), true);
            // Permissions fines RBAC (partagé par PermissionMiddleware) — absent
            // sur les pages hors /admin : tout est visible par défaut.
            $can = $user_can ?? fn(string $k): bool => true;
            $_defSec = ['planning', 'hr', 'requests', 'statistics'];
            $_rawOrd = (array)($user_nav_section_order ?? []);
            $_secOrd = array_values(array_unique(array_merge(
                array_intersect($_rawOrd, $_defSec), $_defSec
            )));
            $reportHref = (is_array($managed_store_ids) && count($managed_store_ids) === 1)
                ? $BASE_URL . '/admin/stores/' . $managed_store_ids[0] . '/employee-report'
                : $BASE_URL . '/admin/stores';
            ?>
            <a href="<?= route_url('home') ?>" class="topbar-nav-link<?= ($path === '/' || $path === '') ? ' topbar-nav-link--active' : '' ?>"><?= __('dashboard') ?></a>

            <?php foreach ($_secOrd as $_sec): switch ($_sec):
                case 'planning': ?>
                    <?php $_canShifts = $feat('shifts') && $can('shifts.view'); ?>
                    <?php $_canTimeclock = $feat('timeclock') && $can('timeclock.view'); ?>
                    <?php if (($_canShifts && !$navHide('shifts')) || ($_canShifts && !$navHide('calendar')) || ($_canShifts && !$navHide('shift_types')) || ($_canTimeclock && !$navHide('timeclocks'))): ?>
                    <div class="topbar-nav-group<?= ($onShifts || $onCalendar || str_starts_with($path, '/admin/shift-types') || str_starts_with($path, '/admin/timeclocks')) ? ' topbar-nav-group--active' : '' ?>">
                        <button type="button" class="topbar-nav-group__trigger"><?= __('planning') ?> <span class="topbar-nav-group__caret">▾</span></button>
                        <div class="topbar-nav-group__panel">
                            <?php if ($_canShifts && !$navHide('shifts')): ?>
                            <a href="<?= route_url('admin.shifts.timeline') ?>" class="topbar-nav-group__link<?= $onShifts ? ' topbar-nav-group__link--active' : '' ?>"><?= $ico('calendar') ?><?= __('shifts') ?></a>
                            <?php endif; ?>
                            <?php if ($_canShifts && !$navHide('calendar')): ?>
                            <a href="<?= route_url('admin.shifts.calendar') ?>" class="topbar-nav-group__link<?= $onCalendar ? ' topbar-nav-group__link--active' : '' ?>"><?= $ico('calendar') ?><?= __('calendar') ?></a>
                            <?php endif; ?>
                            <?php if ($_canShifts && !$navHide('shift_types')): ?>
                            <a href="<?= route_url('admin.shift_types') ?>" class="topbar-nav-group__link<?= str_starts_with($path, '/admin/shift-types') ? ' topbar-nav-group__link--active' : '' ?>"><?= $ico('tag') ?><?= __('shift_types') ?></a>
                            <?php endif; ?>
                            <?php if ($_canTimeclock && !$navHide('timeclocks')): ?>
                            <a href="<?= route_url('admin.timeclocks') ?>" class="topbar-nav-group__link<?= str_starts_with($path, '/admin/timeclocks') ? ' topbar-nav-group__link--active' : '' ?>"><?= $ico('clock') ?><?= __('timeclocks') ?></a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                <?php break; case 'hr': ?>
                    <?php if (($can('employees.view') && !$navHide('users')) || ($can('stores.view') && !$navHide('stores'))): ?>
                    <div class="topbar-nav-group<?= (str_starts_with($path, '/admin/users') || str_starts_with($path, '/admin/stores')) ? ' topbar-nav-group--active' : '' ?>">
                        <button type="button" class="topbar-nav-group__trigger"><?= __('hr') ?> <span class="topbar-nav-group__caret">▾</span></button>
                        <div class="topbar-nav-group__panel">
                            <?php if ($can('employees.view') && !$navHide('users')): ?>
                            <a href="<?= route_url('admin.users') ?>" class="topbar-nav-group__link<?= str_starts_with($path, '/admin/users') ? ' topbar-nav-group__link--active' : '' ?>"><?= $ico('users') ?><?= __('staff') ?></a>
                            <?php endif; ?>
                            <?php if ($can('stores.view') && !$navHide('stores')): ?>
                            <a href="<?= route_url('admin.stores') ?>" class="topbar-nav-group__link<?= str_starts_with($path, '/admin/stores') ? ' topbar-nav-group__link--active' : '' ?>"><?= $ico('store') ?><?= __('stores') ?></a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                <?php break; case 'requests': ?>
                    <?php
                    $_canTimeoff    = $feat('timeoff') && $can('timeoff.view');
                    $_canSwaps      = $feat('swaps') && $can('swaps.view');
                    $_canOpenShifts = $feat('open_shifts') && $can('open_shifts.view');
                    ?>
                    <?php if (($_canTimeoff && !$navHide('timeoff')) || ($_canSwaps && !$navHide('swaps')) || ($_canOpenShifts && !$navHide('open_shifts')) || ($feat('messages') && !$navHide('messages'))): ?>
                    <div class="topbar-nav-group<?= (str_starts_with($path, '/admin/timeoff') || str_starts_with($path, '/admin/swap-requests') || str_starts_with($path, '/admin/open-shifts') || str_starts_with($path, '/admin/messages')) ? ' topbar-nav-group--active' : '' ?>">
                        <button type="button" class="topbar-nav-group__trigger"><?= __('requests') ?> <span class="topbar-nav-group__caret">▾</span></button>
                        <div class="topbar-nav-group__panel">
                            <?php if ($_canTimeoff && !$navHide('timeoff')): ?>
                            <a href="<?= route_url('admin.timeoff') ?>" class="topbar-nav-group__link<?= str_starts_with($path, '/admin/timeoff') ? ' topbar-nav-group__link--active' : '' ?>"><?= $ico('leaf') ?><?= __('timeoff') ?></a>
                            <?php endif; ?>
                            <?php if ($_canSwaps && !$navHide('swaps')): ?>
                            <a href="<?= route_url('admin.swap_requests') ?>" class="topbar-nav-group__link<?= str_starts_with($path, '/admin/swap-requests') ? ' topbar-nav-group__link--active' : '' ?>"><?= $ico('arrows') ?><?= __('swaps') ?></a>
                            <?php endif; ?>
                            <?php if ($_canOpenShifts && !$navHide('open_shifts')): ?>
                            <a href="<?= route_url('admin.open_shifts') ?>" class="topbar-nav-group__link<?= str_starts_with($path, '/admin/open-shifts') ? ' topbar-nav-group__link--active' : '' ?>"><?= $ico('plus') ?><?= __('open_shifts') ?></a>
                            <?php endif; ?>
                            <?php if ($feat('messages') && !$navHide('messages')): ?>
                            <a href="<?= route_url('admin.messages') ?>" class="topbar-nav-group__link<?= str_starts_with($path, '/admin/messages') ? ' topbar-nav-group__link--active' : '' ?>">
                                <?= $ico('message') ?><?= __('messages') ?>
                                <?php if (($unread_messages_count ?? 0) > 0): ?><span class="nav-badge"><?= (int) $unread_messages_count ?></span><?php endif; ?>
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                <?php break; case 'statistics': ?>
                    <?php
                    $_canHiring      = bundle_enabled('hiring-report') && $can('documents.view');
                    $_canResignation = bundle_enabled('resignation-report') && $can('documents.view');
                    $_canSalary      = bundle_enabled('salary-report') && $can('payroll.view');
                    $_canPhotos      = $feat('photos') && $can('documents.view');
                    ?>
                    <?php if ($_canHiring || ($can('payroll.view') && !$navHide('employee_report')) || ($feat('daily_reports') && !$navHide('daily_reports')) || ($_canResignation && !$navHide('resignation_report')) || ($_canSalary && !$navHide('salary_report')) || ($_canPhotos && !$navHide('photos'))): ?>
                    <div class="topbar-nav-group<?= (str_contains($path, '/reports/') || str_contains($path, '/employee-report') || str_contains($path, '/daily-reports') || str_contains($path, '/admin/photos')) ? ' topbar-nav-group--active' : '' ?>">
                        <button type="button" class="topbar-nav-group__trigger"><?= __('reports') ?> <span class="topbar-nav-group__caret">▾</span></button>
                        <div class="topbar-nav-group__panel">
                            <?php if ($_canHiring && !$navHide('hiring_report')): ?>
                            <a href="<?= route_url('admin.reports.hiring') ?>" class="topbar-nav-group__link<?= str_contains($path, '/reports/hiring') ? ' topbar-nav-group__link--active' : '' ?>"><?= $ico('person') ?><?= __('hiring_reports') ?></a>
                            <?php endif; ?>
                            <?php if ($can('payroll.view') && !$navHide('employee_report')): ?>
                            <a href="<?= $reportHref ?>" class="topbar-nav-group__link<?= str_contains($path, '/employee-report') ? ' topbar-nav-group__link--active' : '' ?>"><?= $ico('chart') ?><?= __('employee_report') ?></a>
                            <?php endif; ?>
                            <?php if ($feat('daily_reports') && !$navHide('daily_reports')): ?>
                            <a href="<?= route_url('admin.daily_reports.all') ?>" class="topbar-nav-group__link<?= str_contains($path, '/daily-reports') ? ' topbar-nav-group__link--active' : '' ?>"><?= $ico('chart') ?><?= __('daily_reports') ?></a>
                            <?php endif; ?>
                            <?php if ($_canResignation && !$navHide('resignation_report')): ?>
                            <a href="<?= route_url('admin.reports.resignation') ?>" class="topbar-nav-group__link<?= str_contains($path, '/reports/resignation') ? ' topbar-nav-group__link--active' : '' ?>"><?= $ico('exit') ?><?= __('resignation_report') ?></a>
                            <?php endif; ?>
                            <?php if ($_canSalary && !$navHide('salary_report')): ?>
                            <a href="<?= route_url('admin.reports.salary') ?>" class="topbar-nav-group__link<?= str_contains($path, '/reports/salary') ? ' topbar-nav-group__link--active' : '' ?>"><?= $ico('money') ?><?= __('salary_report') ?></a>
                            <?php endif; ?>
                            <?php if ($_canPhotos && !$navHide('photos')): ?>
                            <a href="<?= route_url('admin.photos.index') ?>" class="topbar-nav-group__link<?= str_contains($path, '/admin/photos') ? ' topbar-nav-group__link--active' : '' ?>"><?= $ico('camera') ?>Photos</a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                <?php break; endswitch; endforeach; ?>

        <?php else: ?>
            <?php
            $navHide = fn(string $k): bool => in_array($k, (array)($user_nav_hidden ?? []), true);
            $_defSec = ['planning', 'requests', 'account'];
            $_rawOrd = (array)($user_nav_section_order ?? []);
            $_secOrd = array_values(array_unique(array_merge(
                array_intersect($_rawOrd, $_defSec), $_defSec
            )));
            ?>
            <a href="<?= route_url('employee.dashboard') ?>" class="topbar-nav-link<?= $path === '/employee' ? ' topbar-nav-link--active' : '' ?>"><?= __('dashboard') ?></a>

            <?php foreach ($_secOrd as $_sec): switch ($_sec):
                case 'planning': ?>
                    <?php if (($feat('shifts') && !$navHide('my_planning')) || ($feat('timeclock') && !$navHide('timeclock'))): ?>
                    <div class="topbar-nav-group<?= (str_starts_with($path, '/employee/shifts') || str_starts_with($path, '/employee/timeclock')) ? ' topbar-nav-group--active' : '' ?>">
                        <button type="button" class="topbar-nav-group__trigger"><?= __('planning') ?> <span class="topbar-nav-group__caret">▾</span></button>
                        <div class="topbar-nav-group__panel">
                            <?php if ($feat('shifts') && !$navHide('my_planning')): ?>
                            <a href="<?= route_url('employee.shifts.day') ?>" class="topbar-nav-group__link<?= str_starts_with($path, '/employee/shifts') ? ' topbar-nav-group__link--active' : '' ?>"><?= $ico('calendar') ?><?= __('my_planning') ?></a>
                            <?php endif; ?>
                            <?php if ($feat('timeclock') && !$navHide('timeclock')): ?>
                            <a href="<?= route_url('employee.timeclock') ?>" class="topbar-nav-group__link<?= str_starts_with($path, '/employee/timeclock') ? ' topbar-nav-group__link--active' : '' ?>"><?= $ico('clock') ?><?= __('timeclock') ?></a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                <?php break; case 'requests': ?>
                    <?php if (($feat('timeoff') && !$navHide('my_timeoff')) || ($feat('swaps') && !$navHide('swaps')) || ($feat('open_shifts') && !$navHide('open_shifts')) || ($feat('messages') && !$navHide('messages'))): ?>
                    <div class="topbar-nav-group<?= (str_starts_with($path, '/employee/timeoff') || str_starts_with($path, '/employee/swaps') || str_starts_with($path, '/employee/open-shifts') || str_starts_with($path, '/employee/messages')) ? ' topbar-nav-group--active' : '' ?>">
                        <button type="button" class="topbar-nav-group__trigger"><?= __('requests') ?> <span class="topbar-nav-group__caret">▾</span></button>
                        <div class="topbar-nav-group__panel">
                            <?php if ($feat('timeoff') && !$navHide('my_timeoff')): ?>
                            <a href="<?= route_url('employee.timeoff') ?>" class="topbar-nav-group__link<?= str_starts_with($path, '/employee/timeoff') ? ' topbar-nav-group__link--active' : '' ?>"><?= $ico('leaf') ?><?= __('my_timeoff') ?></a>
                            <?php endif; ?>
                            <?php if ($feat('swaps') && !$navHide('swaps')): ?>
                            <a href="<?= route_url('employee.swaps') ?>" class="topbar-nav-group__link<?= str_starts_with($path, '/employee/swaps') ? ' topbar-nav-group__link--active' : '' ?>"><?= $ico('arrows') ?><?= __('swaps') ?></a>
                            <?php endif; ?>
                            <?php if ($feat('open_shifts') && !$navHide('open_shifts')): ?>
                            <a href="<?= route_url('employee.open_shifts') ?>" class="topbar-nav-group__link<?= str_starts_with($path, '/employee/open-shifts') ? ' topbar-nav-group__link--active' : '' ?>"><?= $ico('plus') ?><?= __('open_shifts') ?></a>
                            <?php endif; ?>
                            <?php if ($feat('messages') && !$navHide('messages')): ?>
                            <a href="<?= route_url('employee.messages') ?>" class="topbar-nav-group__link<?= str_starts_with($path, '/employee/messages') ? ' topbar-nav-group__link--active' : '' ?>">
                                <?= $ico('message') ?><?= __('messages') ?>
                                <?php if (($unread_messages_count ?? 0) > 0): ?><span class="nav-badge"><?= (int) $unread_messages_count ?></span><?php endif; ?>
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                <?php break; case 'statistics': ?>
                    <?php if ($feat('daily_reports') && !$navHide('daily_reports') && !empty($daily_report_staff_stores)): ?>
                    <div class="topbar-nav-group<?= str_contains($path, '/daily-reports') ? ' topbar-nav-group--active' : '' ?>">
                        <button type="button" class="topbar-nav-group__trigger"><?= __('reports') ?> <span class="topbar-nav-group__caret">▾</span></button>
                        <div class="topbar-nav-group__panel">
                            <?php foreach ($daily_report_staff_stores as $_drStore): ?>
                                <?php $_drId = (int) $_drStore['id']; ?>
                                <a href="<?= route_url('admin.stores') ?>/<?= $_drId ?>/daily-reports"
                                   class="topbar-nav-group__link<?= str_contains($path, '/stores/' . $_drId . '/daily-reports') ? ' topbar-nav-group__link--active' : '' ?>">
                                    <?= $ico('chart') ?><?= __('daily_reports') ?><?= count($daily_report_staff_stores) > 1 ? ' — ' . htmlspecialchars($_drStore['name']) : '' ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                <?php break; case 'account': ?>
                    <?php if (!$navHide('my_profile')): ?>
                    <a href="<?= route_url('profile') ?>" class="topbar-nav-link<?= (str_starts_with($path, '/profile') && ($_GET['tab'] ?? 'info') !== 'nav') ? ' topbar-nav-link--active' : '' ?>"><?= __('my_profile') ?></a>
                    <?php endif; ?>
                <?php break; endswitch; endforeach; ?>
        <?php endif; ?>

        <a href="<?= $BASE_URL ?>/docs" class="topbar-nav-link<?= str_starts_with($path, '/docs') ? ' topbar-nav-link--active' : '' ?>"><?= __('docs') ?></a>
        <a href="<?= $showAdminMenu ? route_url('admin.nav_settings') : (route_url('profile') . '?tab=nav') ?>"
           class="topbar-nav-link<?= ($showAdminMenu ? str_starts_with($path, '/admin/nav-settings') : (str_starts_with($path, '/profile') && ($_GET['tab'] ?? '') === 'nav')) ? ' topbar-nav-link--active' : '' ?>"><?= __('nav_settings') ?></a>
    </nav>

    <?php
    $displayName = htmlspecialchars(
        $auth_user['display_name']
            ?? trim(($auth_user['last_name'] ?? '') . ' ' . ($auth_user['first_name'] ?? ''))
            ?: ($auth_user['email'] ?? __('user'))
    );
    $roleLabel = $isOwner ? __('role_owner') : ($isManager ? __('role_manager') : __('role_employee'));
    $initials  = strtoupper(
        mb_substr($auth_user['first_name'] ?? '', 0, 1) .
        mb_substr($auth_user['last_name']  ?? '', 0, 1)
    );
    if ($initials === '') {
        $initials = strtoupper(mb_substr(strip_tags($displayName), 0, 2));
    }

    $_notifLabels = [
        'message_received'      => __('notif_message_received'),
        'timeoff_approved'      => __('notif_timeoff_approved'),
        'timeoff_refused'       => __('notif_timeoff_refused'),
        'swap_accepted'         => __('notif_swap_accepted'),
        'swap_refused'          => __('notif_swap_refused'),
        'shift_assigned'        => __('notif_shift_assigned'),
        'open_shift_published'  => __('notif_open_shift_published'),
        'shift_claim_submitted' => __('notif_shift_claim_submitted'),
        'shift_claim_approved'  => __('notif_shift_claim_approved'),
        'shift_claim_rejected'  => __('notif_shift_claim_rejected'),
        'shift_claim_withdrawn' => __('notif_shift_claim_withdrawn'),
        'daily_report_submitted' => __('notif_daily_report_submitted'),
        'daily_report_validated' => __('notif_daily_report_validated'),
        'feedback_submitted'    => __('notif_feedback_submitted'),
        'swap_requested'        => __('notif_swap_requested'),
        'swap_peer_accepted'    => __('notif_swap_peer_accepted'),
        'swap_peer_refused'     => __('notif_swap_peer_refused'),
        'swap_cancelled'        => __('notif_swap_cancelled'),
    ];
    $_notifIcons = [
        'message_received'      => '✉',
        'timeoff_approved'      => '✓',
        'timeoff_refused'       => '✗',
        'swap_accepted'         => '⇄',
        'swap_refused'          => '⇄',
        'shift_assigned'        => '📅',
        'open_shift_published'  => '📢',
        'shift_claim_submitted' => '🙋',
        'shift_claim_approved'  => '✓',
        'shift_claim_rejected'  => '✗',
        'shift_claim_withdrawn' => '⊘',
        'daily_report_submitted' => '📝',
        'daily_report_validated' => '✓',
        'feedback_submitted'    => '💬',
        'swap_requested'        => '⇄',
        'swap_peer_accepted'    => '⇄',
        'swap_peer_refused'     => '⇄',
        'swap_cancelled'        => '⇄',
    ];
    $_dropdownItems = $notifications_dropdown ?? [];
    $_unreadCount   = (int) ($unread_notifications_count ?? 0);
    $_recentJson    = json_encode(array_map(fn($n) => [
        'title' => $_notifLabels[$n['type'] ?? ''] ?? ($n['type'] ?? ''),
        'body'  => $n['body'] ?? '',
    ], $recent_notifications ?? []), JSON_UNESCAPED_UNICODE);
    ?>

    <div class="topbar-actions">

        <?php if (!$showAdminMenu && isset($employee_month_stats)): ?>
            <?php $ems = $employee_month_stats;
            $emsCur = $ems['currency'] ?? 'JPY';
            $emsStyle = $ems['currency_symbol_style'] ?? 'kanji'; ?>
            <div class="topbar-nav-group topbar-nav-group--stats">
                <button type="button" class="topbar-nav-group__trigger topbar-nav-group__trigger--stats">
                    <?= $ico('clock') ?><?= number_format($ems['hours_month'], 1) ?> h <span class="topbar-nav-group__caret">▾</span>
                </button>
                <div class="topbar-nav-group__panel topbar-nav-group__panel--stats">
                    <div class="sb-month-nav">
                        <button onclick="sbMonthNav('<?= htmlspecialchars($ems['prev_month']) ?>')" class="sb-month-btn">&larr;</button>
                        <span class="sb-month-label"><?= htmlspecialchars($ems['month_label']) ?></span>
                        <button onclick="sbMonthNav('<?= htmlspecialchars($ems['next_month']) ?>')" class="sb-month-btn" <?= $ems['is_current'] ? 'disabled' : '' ?>>&rarr;</button>
                    </div>
                    <div class="sb-stats-card">
                        <div class="sb-stats-row">
                            <span class="sb-stats-label"><?= __('hours') ?></span>
                            <strong class="sb-stats-value--primary"><?= number_format($ems['hours_month'], 1) ?> h</strong>
                        </div>
                        <div class="sb-stats-row">
                            <span class="sb-stats-label"><?= __('avg_per_week') ?></span>
                            <strong><?= number_format($ems['hours_week'], 1) ?> h</strong>
                        </div>
                        <?php if ($ems['has_rate']): ?>
                            <div class="sb-stats-row sb-stats-row--border">
                                <span class="sb-stats-label"><?= __('estimated_pay') ?></span>
                                <strong class="sb-stats-value--success"><?= format_currency((float) $ems['estimated_pay'], $emsCur, $emsStyle) ?></strong>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($ems['shift_details'])): ?>
                            <button onclick="sbDetailOpen()" class="sb-detail-btn">
                                <?= __('see_details') ?> (<?= count($ems['shift_details']) ?> shift<?= count($ems['shift_details']) > 1 ? 's' : '' ?>) &#9654;
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <?php if (!empty($ems['shift_details'])): ?>
                <div id="sb-detail-overlay" onclick="sbDetailClose()">
                    <div class="sb-modal" onclick="event.stopPropagation()">
                        <div class="sb-modal-header">
                            <strong><?= __('shift_detail_title', ['month' => htmlspecialchars($ems['month_label'])]) ?></strong>
                            <button onclick="sbDetailClose()" class="sb-modal-close">&times;</button>
                        </div>
                        <div class="sb-modal-body">
                            <table class="sb-modal-table">
                                <thead>
                                    <tr>
                                        <th><?= __('date') ?></th>
                                        <th><?= __('schedule') ?></th>
                                        <th><?= __('type') ?></th>
                                        <th><?= __('net') ?></th>
                                        <?php if ($ems['has_rate']): ?>
                                            <th><?= __('rate_h') ?></th>
                                            <th class="th-right"><?= __('estimated_pay') ?></th>
                                        <?php endif; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php $sbTotalNet = 0; ?>
                                    <?php foreach ($ems['shift_details'] as $row): ?>
                                        <?php $sbTotalNet += $row['net_min'] ?? 0; ?>
                                        <tr>
                                            <td class="td-nowrap"><?= htmlspecialchars($row['date_label']) ?></td>
                                            <td class="td-mono"><?= htmlspecialchars($row['start']) ?>&ndash;<?= htmlspecialchars($row['end']) ?></td>
                                            <td><?= htmlspecialchars($row['type_name']) ?></td>
                                            <td class="td-nowrap">
                                                <?= htmlspecialchars($row['net_hours_fmt']) ?>
                                                <?php if (($row['pause_min'] ?? 0) > 0): ?>
                                                    <small class="sb-stats-label">(<?= (int)$row['pause_min'] ?> min)</small>
                                                <?php endif; ?>
                                            </td>
                                            <?php if ($ems['has_rate']): ?>
                                                <td class="td-muted"><?= htmlspecialchars($row['rate_fmt']) ?></td>
                                                <td class="td-right <?= $row['has_rate'] ? 'sb-pay-ok' : 'sb-pay-none' ?>">
                                                    <?= htmlspecialchars($row['pay_fmt']) ?>
                                                </td>
                                            <?php endif; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <td colspan="3" class="td-muted"><?= __('total') ?></td>
                                        <td class="td-nowrap">
                                            <?php $sbH = intdiv($sbTotalNet, 60);
                                            $sbM = $sbTotalNet % 60; ?>
                                            <?= $sbH ?>h<?= str_pad((string)$sbM, 2, '0', STR_PAD_LEFT) ?>
                                        </td>
                                        <?php if ($ems['has_rate']): ?>
                                            <td></td>
                                            <td class="td-right sb-stats-value--success"><?= format_currency((float) $ems['estimated_pay'], $emsCur, $emsStyle) ?></td>
                                        <?php endif; ?>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                        <div class="sb-modal-footer">
                            <button onclick="sbDetailClose()" class="btn btn--ghost btn--sm"><?= __('close') ?></button>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            <script src="<?= $BASE_URL ?>/assets/js/modules/sidebar-stats.js"></script>
        <?php endif; ?>

        <div class="notif-dropdown" id="notif-dropdown">
            <button id="notif-toggle" class="notif-dropdown__toggle" title="<?= __('notifications') ?>">
                🔔
                <?php if ($_unreadCount > 0): ?>
                    <span class="notif-dropdown__badge"><?= min($_unreadCount, 99) ?></span>
                <?php endif; ?>
            </button>
            <div class="notif-dropdown__panel">
                <div class="notif-dropdown__header">
                    <span><?= __('notifications') ?></span>
                    <?php if ($_unreadCount > 0): ?>
                        <form method="POST" action="<?= route_url('notifications.read_all') ?>" class="notif-mark-read-form">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn btn--ghost btn--xs"><?= __('mark_all_read') ?></button>
                        </form>
                    <?php endif; ?>
                </div>
                <?php if (empty($_dropdownItems)): ?>
                    <div class="notif-dropdown__empty"><?= __('no_notifications') ?></div>
                <?php else: ?>
                    <?php foreach ($_dropdownItems as $_n): ?>
                        <?php $_nRead = (int) ($_n['is_read'] ?? 0); ?>
                        <div class="notif-entry<?= $_nRead ? '' : ' notif-entry--unread' ?>">
                            <span class="notif-entry__icon"><?= $_notifIcons[$_n['type'] ?? ''] ?? '•' ?></span>
                            <div class="notif-entry__body">
                                <div class="notif-entry__title"><?= htmlspecialchars($_notifLabels[$_n['type'] ?? ''] ?? '') ?></div>
                                <div class="notif-entry__text"><?= htmlspecialchars($_n['body'] ?? '') ?></div>
                                <div class="notif-entry__time"><?= htmlspecialchars(substr($_n['created_at'] ?? '', 0, 16)) ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <div class="notif-dropdown__footer">
                        <a href="<?= route_url('notifications.index') ?>"><?= __('notifications') ?> →</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <div id="notif-meta"
             data-recent="<?= htmlspecialchars($_recentJson) ?>"
             data-poll-url="<?= route_url('notifications.poll') ?>"
             data-labels="<?= htmlspecialchars(json_encode($_notifLabels, JSON_UNESCAPED_UNICODE)) ?>"
             hidden></div>

        <div class="user-dropdown" id="user-dropdown">
            <button class="user-dropdown__toggle" id="user-toggle"
                    aria-expanded="false" aria-haspopup="true">
                <span class="user-dropdown__avatar"><?= htmlspecialchars($initials) ?></span>
                <span class="user-dropdown__label"><?= $displayName ?></span>
                <span class="user-dropdown__chevron" aria-hidden="true">▾</span>
            </button>

            <div class="user-dropdown__panel" role="menu">
                <div class="user-dropdown__header">
                    <div class="user-dropdown__hname"><?= $displayName ?></div>
                    <div class="user-dropdown__hrole"><?= $roleLabel ?></div>
                </div>

                <a href="<?= route_url('profile') ?>" class="user-dropdown__item" role="menuitem">
                    <span class="user-dropdown__item-icon">👤</span><?= __('my_profile') ?>
                </a>

                <hr class="user-dropdown__divider">

                <button id="themeToggle" class="user-dropdown__item" role="menuitem" type="button">
                    <span class="user-dropdown__item-icon" id="theme-icon">🌙</span>
                    <span id="theme-label">Mode sombre</span>
                </button>

                <div class="user-dropdown__item user-dropdown__lang">
                    <span class="user-dropdown__lang-left">
                        <span class="user-dropdown__item-icon">🌐</span><?= __('language') ?>
                    </span>
                    <div class="user-dropdown__lang-btns">
                        <?php foreach (($active_languages ?? []) as $_lang): ?>
                        <a href="<?= $BASE_URL ?>/lang/<?= htmlspecialchars($_lang['code']) ?>"
                           title="<?= htmlspecialchars($_lang['name']) ?>"
                           class="user-dropdown__lang-btn<?= ($locale ?? 'en') === $_lang['code'] ? ' user-dropdown__lang-btn--active' : '' ?>"><?= htmlspecialchars(strtoupper($_lang['code'])) ?></a>
                        <?php endforeach; ?>
                    </div>
                </div>

                <?php if (($auth_is_manager ?? $isManager) && !$isOwner): ?>
                <hr class="user-dropdown__divider">
                <form method="POST" action="<?= route_url('switch.view') ?>" class="form-contents">
                    <?= csrf_field() ?>
                    <button type="submit" class="user-dropdown__item" role="menuitem">
                        <span class="user-dropdown__item-icon">🔄</span>
                        <?= $viewMode === 'admin' ? __('view_employee') : __('view_admin') ?>
                    </button>
                </form>
                <?php endif; ?>

                <hr class="user-dropdown__divider">

                <form method="POST" action="<?= route_url('auth.logout') ?>" class="form-contents">
                    <?= csrf_field() ?>
                    <button type="submit" class="user-dropdown__item user-dropdown__item--danger" role="menuitem">
                        <span class="user-dropdown__item-icon">⎋</span><?= __('logout') ?>
                    </button>
                </form>
            </div>
        </div>

    </div>
</header>
