<?php
$onShifts         = str_starts_with($path, '/admin/shifts') && !str_contains($path, 'shift-type');
$onShiftsTimeline = str_starts_with($path, '/admin/shifts/timeline');
$onCalendar       = str_starts_with($path, '/admin/shifts/calendar');
$ico              = fn(string $k): string => '<span class="sidebar-link__icon">' . $svgIcon($k) . '</span>';
?>
<nav class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <div class="sidebar-brand">
            <span class="sidebar-brand__name">Kintai</span>
            <?php if (!empty($app_subtitle)): ?>
                <span class="sidebar-brand__subtitle"><?= htmlspecialchars($app_subtitle, ENT_QUOTES) ?></span>
            <?php endif; ?>
        </div>
        <button type="button" class="sidebar-close" id="sidebarClose" aria-label="Fermer le menu">&times;</button>
    </div>
    <ul class="sidebar-nav">
        <?php if ($showAdminMenu && $isOwner): ?>
            <?php
            $navHide = fn(string $k): bool => in_array($k, (array)($user_nav_hidden ?? []), true);
            $_defSec = ['planning', 'hr', 'requests', 'statistics', 'system'];
            $_rawOrd = (array)($user_nav_section_order ?? []);
            $_secOrd = array_values(array_unique(array_merge(
                array_intersect($_rawOrd, $_defSec), $_defSec
            )));
            ?>
            <li><a href="<?= route_url('home') ?>" class="sidebar-link<?= ($path === '/' || $path === '') ? ' active' : '' ?>"><?= $ico('home') ?><?= __('dashboard') ?></a></li>

            <?php foreach ($_secOrd as $_sec): switch ($_sec):
                case 'planning': ?>
                    <?php if (!$navHide('shifts') || !$navHide('calendar') || !$navHide('shift_types') || ($feat('timeclock') && !$navHide('timeclocks'))): ?>
                    <li class="sidebar-section"><?= __('planning') ?></li>
                    <?php if (!$navHide('shifts')): ?>
                    <li><a href="<?= route_url('admin.shifts.timeline') ?>" class="sidebar-link<?= $onShifts ? ' active' : '' ?>"><?= $ico('calendar') ?><?= __('shifts') ?></a></li>
                    <?php endif; ?>
                    <?php if (!$navHide('calendar')): ?>
                    <li><a href="<?= route_url('admin.shifts.calendar') ?>" class="sidebar-link<?= $onCalendar ? ' active' : '' ?>"><?= $ico('calendar') ?><?= __('calendar') ?></a></li>
                    <?php endif; ?>
                    <?php if (!$navHide('shift_types')): ?>
                    <li><a href="<?= route_url('admin.shift_types') ?>" class="sidebar-link<?= str_starts_with($path, '/admin/shift-types') ? ' active' : '' ?>"><?= $ico('tag') ?><?= __('shift_types') ?></a></li>
                    <?php endif; ?>
                    <?php if ($feat('timeclock') && !$navHide('timeclocks')): ?>
                    <li><a href="<?= route_url('admin.timeclocks') ?>" class="sidebar-link<?= str_starts_with($path, '/admin/timeclocks') ? ' active' : '' ?>"><?= $ico('clock') ?><?= __('timeclocks') ?></a></li>
                    <?php endif; ?>
                    <?php endif; ?>
                <?php break; case 'hr': ?>
                    <?php if (!$navHide('users') || !$navHide('stores')): ?>
                    <li class="sidebar-section"><?= __('hr') ?></li>
                    <?php if (!$navHide('users')): ?>
                    <li><a href="<?= route_url('admin.users') ?>" class="sidebar-link<?= str_starts_with($path, '/admin/users') ? ' active' : '' ?>"><?= $ico('users') ?><?= __('users') ?></a></li>
                    <?php endif; ?>
                    <?php if (!$navHide('stores')): ?>
                    <li><a href="<?= route_url('admin.stores') ?>" class="sidebar-link<?= str_starts_with($path, '/admin/stores') ? ' active' : '' ?>"><?= $ico('store') ?><?= __('stores') ?></a></li>
                    <?php endif; ?>
                    <?php endif; ?>
                <?php break; case 'requests': ?>
                    <?php if (($feat('timeoff') && !$navHide('timeoff')) || ($feat('swaps') && !$navHide('swaps')) || ($feat('open_shifts') && !$navHide('open_shifts')) || ($feat('messages') && !$navHide('messages'))): ?>
                    <li class="sidebar-section"><?= __('requests') ?></li>
                    <?php if ($feat('timeoff') && !$navHide('timeoff')): ?>
                    <li><a href="<?= route_url('admin.timeoff') ?>" class="sidebar-link<?= str_starts_with($path, '/admin/timeoff') ? ' active' : '' ?>"><?= $ico('leaf') ?><?= __('timeoff') ?></a></li>
                    <?php endif; ?>
                    <?php if ($feat('swaps') && !$navHide('swaps')): ?>
                    <li><a href="<?= route_url('admin.swap_requests') ?>" class="sidebar-link<?= str_starts_with($path, '/admin/swap-requests') ? ' active' : '' ?>"><?= $ico('arrows') ?><?= __('swaps') ?></a></li>
                    <?php endif; ?>
                    <?php if ($feat('open_shifts') && !$navHide('open_shifts')): ?>
                    <li><a href="<?= route_url('admin.open_shifts') ?>" class="sidebar-link<?= str_starts_with($path, '/admin/open-shifts') ? ' active' : '' ?>"><?= $ico('plus') ?><?= __('open_shifts') ?></a></li>
                    <?php endif; ?>
                    <?php if ($feat('messages') && !$navHide('messages')): ?>
                    <li>
                        <a href="<?= route_url('admin.messages') ?>" class="sidebar-link<?= str_starts_with($path, '/admin/messages') ? ' active' : '' ?>">
                            <?= $ico('message') ?><?= __('messages') ?>
                            <?php if (($unread_messages_count ?? 0) > 0): ?>
                                <span class="nav-badge"><?= (int) $unread_messages_count ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <?php endif; ?>
                    <?php endif; ?>
                <?php break; case 'statistics': ?>
                    <?php if ((bundle_enabled('hiring-report') && !$navHide('hiring_report')) || ($feat('daily_reports') && !$navHide('daily_reports')) || (bundle_enabled('resignation-report') && !$navHide('resignation_report')) || (bundle_enabled('salary-report') && !$navHide('salary_report')) || ($feat('photos') && !$navHide('photos'))): ?>
                    <li class="sidebar-section"><?= __('reports') ?></li>
                    <?php if (bundle_enabled('hiring-report') && !$navHide('hiring_report')): ?>
                    <li><a href="<?= route_url('admin.reports.hiring') ?>" class="sidebar-link<?= str_contains($path, '/reports/hiring') ? ' active' : '' ?>"><?= $ico('person') ?><?= __('hiring_reports') ?></a></li>
                    <?php endif; ?>
                    <?php if ($feat('daily_reports') && !$navHide('daily_reports')): ?>
                    <li><a href="<?= route_url('admin.daily_reports.all') ?>" class="sidebar-link<?= str_contains($path, '/daily-reports') ? ' active' : '' ?>"><?= $ico('chart') ?><?= __('daily_reports') ?></a></li>
                    <?php endif; ?>
                    <?php if (bundle_enabled('resignation-report') && !$navHide('resignation_report')): ?>
                    <li><a href="<?= route_url('admin.reports.resignation') ?>" class="sidebar-link<?= str_contains($path, '/reports/resignation') ? ' active' : '' ?>"><?= $ico('exit') ?><?= __('resignation_report') ?></a></li>
                    <?php endif; ?>
                    <?php if (bundle_enabled('salary-report') && !$navHide('salary_report')): ?>
                    <li><a href="<?= route_url('admin.reports.salary') ?>" class="sidebar-link<?= str_contains($path, '/reports/salary') ? ' active' : '' ?>"><?= $ico('money') ?><?= __('salary_report') ?></a></li>
                    <?php endif; ?>
                    <?php if ($feat('photos') && !$navHide('photos')): ?>
                    <li><a href="<?= route_url('admin.photos.index') ?>" class="sidebar-link<?= str_contains($path, '/admin/photos') ? ' active' : '' ?>"><?= $ico('camera') ?><?= __('photos_report') ?></a></li>
                    <?php endif; ?>
                    <?php endif; ?>
                <?php break; case 'system': ?>
                    <li class="sidebar-section"><?= __('system') ?></li>
                    <?php if (!$navHide('audit_log')): ?>
                    <li><a href="<?= route_url('admin.activity') ?>" class="sidebar-link<?= str_starts_with($path, '/admin/activity') ? ' active' : '' ?>"><?= $ico('history') ?><?= __('activity_log') ?></a></li>
                    <?php endif; ?>
                    <?php if ($isOwner): ?>
                    <li><a href="<?= route_url('admin.owner_settings') ?>" class="sidebar-link<?= (str_starts_with($path, '/admin/owner-settings') || str_starts_with($path, '/admin/feedbacks') || str_starts_with($path, '/admin/backup') || str_starts_with($path, '/admin/update') || str_starts_with($path, '/admin/languages') || str_starts_with($path, '/admin/bundles')) ? ' active' : '' ?>"><?= $ico('gear') ?><?= __('settings') ?></a></li>
                    <?php endif; ?>
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
            <li><a href="<?= route_url('home') ?>" class="sidebar-link<?= ($path === '/' || $path === '') ? ' active' : '' ?>"><?= $ico('home') ?><?= __('dashboard') ?></a></li>

            <?php foreach ($_secOrd as $_sec): switch ($_sec):
                case 'planning': ?>
                    <?php $_canShifts = $feat('shifts') && $can('shifts.view'); ?>
                    <?php $_canTimeclock = $feat('timeclock') && $can('timeclock.view'); ?>
                    <?php if (($_canShifts && !$navHide('shifts')) || ($_canShifts && !$navHide('calendar')) || ($_canShifts && !$navHide('shift_types')) || ($_canTimeclock && !$navHide('timeclocks'))): ?>
                    <li class="sidebar-section"><?= __('planning') ?></li>
                    <?php if ($_canShifts && !$navHide('shifts')): ?>
                    <li><a href="<?= route_url('admin.shifts.timeline') ?>" class="sidebar-link<?= $onShifts ? ' active' : '' ?>"><?= $ico('calendar') ?><?= __('shifts') ?></a></li>
                    <?php endif; ?>
                    <?php if ($_canShifts && !$navHide('calendar')): ?>
                    <li><a href="<?= route_url('admin.shifts.calendar') ?>" class="sidebar-link<?= $onCalendar ? ' active' : '' ?>"><?= $ico('calendar') ?><?= __('calendar') ?></a></li>
                    <?php endif; ?>
                    <?php if ($_canShifts && !$navHide('shift_types')): ?>
                    <li><a href="<?= route_url('admin.shift_types') ?>" class="sidebar-link<?= str_starts_with($path, '/admin/shift-types') ? ' active' : '' ?>"><?= $ico('tag') ?><?= __('shift_types') ?></a></li>
                    <?php endif; ?>
                    <?php if ($_canTimeclock && !$navHide('timeclocks')): ?>
                    <li><a href="<?= route_url('admin.timeclocks') ?>" class="sidebar-link<?= str_starts_with($path, '/admin/timeclocks') ? ' active' : '' ?>"><?= $ico('clock') ?><?= __('timeclocks') ?></a></li>
                    <?php endif; ?>
                    <?php endif; ?>
                <?php break; case 'hr': ?>
                    <?php if (($can('employees.view') && !$navHide('users')) || ($can('stores.view') && !$navHide('stores'))): ?>
                    <li class="sidebar-section"><?= __('hr') ?></li>
                    <?php if ($can('employees.view') && !$navHide('users')): ?>
                    <li><a href="<?= route_url('admin.users') ?>" class="sidebar-link<?= str_starts_with($path, '/admin/users') ? ' active' : '' ?>"><?= $ico('users') ?><?= __('staff') ?></a></li>
                    <?php endif; ?>
                    <?php if ($can('stores.view') && !$navHide('stores')): ?>
                    <li><a href="<?= route_url('admin.stores') ?>" class="sidebar-link<?= str_starts_with($path, '/admin/stores') ? ' active' : '' ?>"><?= $ico('store') ?><?= __('stores') ?></a></li>
                    <?php endif; ?>
                    <?php endif; ?>
                <?php break; case 'requests': ?>
                    <?php
                    $_canTimeoff    = $feat('timeoff') && $can('timeoff.view');
                    $_canSwaps      = $feat('swaps') && $can('swaps.view');
                    $_canOpenShifts = $feat('open_shifts') && $can('open_shifts.view');
                    ?>
                    <?php if (($_canTimeoff && !$navHide('timeoff')) || ($_canSwaps && !$navHide('swaps')) || ($_canOpenShifts && !$navHide('open_shifts')) || ($feat('messages') && !$navHide('messages'))): ?>
                    <li class="sidebar-section"><?= __('requests') ?></li>
                    <?php if ($_canTimeoff && !$navHide('timeoff')): ?>
                    <li><a href="<?= route_url('admin.timeoff') ?>" class="sidebar-link<?= str_starts_with($path, '/admin/timeoff') ? ' active' : '' ?>"><?= $ico('leaf') ?><?= __('timeoff') ?></a></li>
                    <?php endif; ?>
                    <?php if ($_canSwaps && !$navHide('swaps')): ?>
                    <li><a href="<?= route_url('admin.swap_requests') ?>" class="sidebar-link<?= str_starts_with($path, '/admin/swap-requests') ? ' active' : '' ?>"><?= $ico('arrows') ?><?= __('swaps') ?></a></li>
                    <?php endif; ?>
                    <?php if ($_canOpenShifts && !$navHide('open_shifts')): ?>
                    <li><a href="<?= route_url('admin.open_shifts') ?>" class="sidebar-link<?= str_starts_with($path, '/admin/open-shifts') ? ' active' : '' ?>"><?= $ico('plus') ?><?= __('open_shifts') ?></a></li>
                    <?php endif; ?>
                    <?php if ($feat('messages') && !$navHide('messages')): ?>
                    <li>
                        <a href="<?= route_url('admin.messages') ?>" class="sidebar-link<?= str_starts_with($path, '/admin/messages') ? ' active' : '' ?>">
                            <?= $ico('message') ?><?= __('messages') ?>
                            <?php if (($unread_messages_count ?? 0) > 0): ?>
                                <span class="nav-badge"><?= (int) $unread_messages_count ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <?php endif; ?>
                    <?php endif; ?>
                <?php break; case 'statistics': ?>
                    <?php
                    $_canHiring      = bundle_enabled('hiring-report') && $can('documents.view');
                    $_canResignation = bundle_enabled('resignation-report') && $can('documents.view');
                    $_canSalary      = bundle_enabled('salary-report') && $can('payroll.view');
                    $_canPhotos      = $feat('photos') && $can('documents.view');
                    ?>
                    <?php if ($_canHiring || ($can('payroll.view') && !$navHide('employee_report')) || ($feat('daily_reports') && !$navHide('daily_reports')) || ($_canResignation && !$navHide('resignation_report')) || ($_canSalary && !$navHide('salary_report')) || ($_canPhotos && !$navHide('photos'))): ?>
                    <li class="sidebar-section"><?= __('reports') ?></li>
                    <?php if ($_canHiring && !$navHide('hiring_report')): ?>
                    <li><a href="<?= route_url('admin.reports.hiring') ?>" class="sidebar-link<?= str_contains($path, '/reports/hiring') ? ' active' : '' ?>"><?= $ico('person') ?><?= __('hiring_reports') ?></a></li>
                    <?php endif; ?>
                    <?php if ($can('payroll.view') && !$navHide('employee_report')): ?>
                    <li><a href="<?= $reportHref ?>" class="sidebar-link<?= str_contains($path, '/employee-report') ? ' active' : '' ?>"><?= $ico('chart') ?><?= __('employee_report') ?></a></li>
                    <?php endif; ?>
                    <?php if ($feat('daily_reports') && !$navHide('daily_reports')): ?>
                    <li><a href="<?= route_url('admin.daily_reports.all') ?>" class="sidebar-link<?= str_contains($path, '/daily-reports') ? ' active' : '' ?>"><?= $ico('chart') ?><?= __('daily_reports') ?></a></li>
                    <?php endif; ?>
                    <?php if ($_canResignation && !$navHide('resignation_report')): ?>
                    <li><a href="<?= route_url('admin.reports.resignation') ?>" class="sidebar-link<?= str_contains($path, '/reports/resignation') ? ' active' : '' ?>"><?= $ico('exit') ?><?= __('resignation_report') ?></a></li>
                    <?php endif; ?>
                    <?php if ($_canSalary && !$navHide('salary_report')): ?>
                    <li><a href="<?= route_url('admin.reports.salary') ?>" class="sidebar-link<?= str_contains($path, '/reports/salary') ? ' active' : '' ?>"><?= $ico('money') ?><?= __('salary_report') ?></a></li>
                    <?php endif; ?>
                    <?php if ($_canPhotos && !$navHide('photos')): ?>
                    <li><a href="<?= route_url('admin.photos.index') ?>" class="sidebar-link<?= str_contains($path, '/admin/photos') ? ' active' : '' ?>"><?= $ico('camera') ?>Photos</a></li>
                    <?php endif; ?>
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
            <li><a href="<?= route_url('employee.dashboard') ?>" class="sidebar-link<?= $path === '/employee' ? ' active' : '' ?>"><?= $ico('home') ?><?= __('dashboard') ?></a></li>

            <?php foreach ($_secOrd as $_sec): switch ($_sec):
                case 'planning': ?>
                    <?php if (($feat('shifts') && !$navHide('my_planning')) || ($feat('timeclock') && !$navHide('timeclock'))): ?>
                    <li class="sidebar-section"><?= __('planning') ?></li>
                    <?php if ($feat('shifts') && !$navHide('my_planning')): ?>
                    <li><a href="<?= route_url('employee.shifts.day') ?>" class="sidebar-link<?= str_starts_with($path, '/employee/shifts') ? ' active' : '' ?>"><?= $ico('calendar') ?><?= __('my_planning') ?></a></li>
                    <?php endif; ?>
                    <?php if ($feat('timeclock') && !$navHide('timeclock')): ?>
                    <li><a href="<?= route_url('employee.timeclock') ?>" class="sidebar-link<?= str_starts_with($path, '/employee/timeclock') ? ' active' : '' ?>"><?= $ico('clock') ?><?= __('timeclock') ?></a></li>
                    <?php endif; ?>
                    <?php endif; ?>
                <?php break; case 'requests': ?>
                    <?php if (($feat('timeoff') && !$navHide('my_timeoff')) || ($feat('swaps') && !$navHide('swaps')) || ($feat('open_shifts') && !$navHide('open_shifts')) || ($feat('messages') && !$navHide('messages'))): ?>
                    <li class="sidebar-section"><?= __('requests') ?></li>
                    <?php if ($feat('timeoff') && !$navHide('my_timeoff')): ?>
                    <li><a href="<?= route_url('employee.timeoff') ?>" class="sidebar-link<?= str_starts_with($path, '/employee/timeoff') ? ' active' : '' ?>"><?= $ico('leaf') ?><?= __('my_timeoff') ?></a></li>
                    <?php endif; ?>
                    <?php if ($feat('swaps') && !$navHide('swaps')): ?>
                    <li><a href="<?= route_url('employee.swaps') ?>" class="sidebar-link<?= str_starts_with($path, '/employee/swaps') ? ' active' : '' ?>"><?= $ico('arrows') ?><?= __('swaps') ?></a></li>
                    <?php endif; ?>
                    <?php if ($feat('open_shifts') && !$navHide('open_shifts')): ?>
                    <li><a href="<?= route_url('employee.open_shifts') ?>" class="sidebar-link<?= str_starts_with($path, '/employee/open-shifts') ? ' active' : '' ?>"><?= $ico('plus') ?><?= __('open_shifts') ?></a></li>
                    <?php endif; ?>
                    <?php if ($feat('messages') && !$navHide('messages')): ?>
                    <li>
                        <a href="<?= route_url('employee.messages') ?>" class="sidebar-link<?= str_starts_with($path, '/employee/messages') ? ' active' : '' ?>">
                            <?= $ico('message') ?><?= __('messages') ?>
                            <?php if (($unread_messages_count ?? 0) > 0): ?>
                                <span class="nav-badge"><?= (int) $unread_messages_count ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <?php endif; ?>
                    <?php endif; ?>
                <?php break; case 'statistics': ?>
                    <?php if ($feat('daily_reports') && !$navHide('daily_reports') && !empty($daily_report_staff_stores)): ?>
                    <li class="sidebar-section"><?= __('reports') ?></li>
                    <?php foreach ($daily_report_staff_stores as $_drStore): ?>
                        <?php $_drId = (int) $_drStore['id']; ?>
                        <li><a href="<?= route_url('admin.stores') ?>/<?= $_drId ?>/daily-reports"
                               class="sidebar-link<?= str_contains($path, '/stores/' . $_drId . '/daily-reports') ? ' active' : '' ?>">
                            <?= $ico('chart') ?><?= __('daily_reports') ?><?= count($daily_report_staff_stores) > 1 ? ' — ' . htmlspecialchars($_drStore['name']) : '' ?>
                        </a></li>
                    <?php endforeach; ?>
                    <?php endif; ?>
                <?php break; case 'account': ?>
                    <?php if (!$navHide('my_profile')): ?>
                    <li class="sidebar-section"><?= __('account') ?></li>
                    <li><a href="<?= route_url('profile') ?>" class="sidebar-link<?= (str_starts_with($path, '/profile') && ($_GET['tab'] ?? 'info') !== 'nav') ? ' active' : '' ?>"><?= $ico('person') ?><?= __('my_profile') ?></a></li>
                    <?php endif; ?>
                <?php break; endswitch; endforeach; ?>
        <?php endif; ?>
    </ul>

    <?php if (!$showAdminMenu && isset($employee_month_stats)): ?>
        <?php $ems = $employee_month_stats;
        $emsCur = $ems['currency'] ?? 'JPY'; ?>
        <div class="sb-stats">
            <div class="sb-month-nav">
                <button onclick="sbMonthNav('<?= htmlspecialchars($ems['prev_month']) ?>')"
                    class="sb-month-btn">&larr;</button>
                <span class="sb-month-label"><?= htmlspecialchars($ems['month_label']) ?></span>
                <button onclick="sbMonthNav('<?= htmlspecialchars($ems['next_month']) ?>')"
                    class="sb-month-btn"
                    <?= $ems['is_current'] ? 'disabled' : '' ?>>&rarr;</button>
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
                        <strong class="sb-stats-value--success"><?= format_currency((float) $ems['estimated_pay'], $emsCur) ?></strong>
                    </div>
                <?php endif; ?>
                <?php if (!empty($ems['shift_details'])): ?>
                    <button onclick="sbDetailOpen()" class="sb-detail-btn">
                        <?= __('see_details') ?> (<?= count($ems['shift_details']) ?> shift<?= count($ems['shift_details']) > 1 ? 's' : '' ?>) &#9654;
                    </button>
                <?php endif; ?>
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
                                        <td class="td-right sb-stats-value--success"><?= format_currency((float) $ems['estimated_pay'], $emsCur) ?></td>
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

    <div class="sidebar-nav-footer">
        <a href="<?= $BASE_URL ?>/docs"
           class="sidebar-link sidebar-nav-footer__link<?= str_starts_with($path, '/docs') ? ' active' : '' ?>">
            <?= $ico('book') ?><?= __('docs') ?>
        </a>
        <a href="<?= $showAdminMenu ? route_url('admin.nav_settings') : (route_url('profile') . '?tab=nav') ?>"
           class="sidebar-link sidebar-nav-footer__link<?= ($showAdminMenu ? str_starts_with($path, '/admin/nav-settings') : (str_starts_with($path, '/profile') && ($_GET['tab'] ?? '') === 'nav')) ? ' active' : '' ?>">
            <?= $ico('gear') ?><?= __('nav_settings') ?>
        </a>
    </div>
</nav>
