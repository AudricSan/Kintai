<?php
$onShifts         = str_starts_with($path, '/admin/shifts') && !str_contains($path, 'shift-type');
$onShiftsTimeline = str_starts_with($path, '/admin/shifts/timeline');
$onCalendar       = str_starts_with($path, '/admin/shifts/calendar');
$ico              = fn(string $k): string => '<span class="topbar-nav-group__link-icon">' . $svgIcon($k) . '</span>';
?>
<header class="topbar">
    <a href="<?= $isManager ? route_url('home') : route_url('employee.dashboard') ?>" class="topbar-brand"><img src="<?= $BASE_URL ?>/assets/img/<?= mascot_path('brand-icon') ?>" alt="" class="topbar-brand__icon">Kintai<?php if (!empty($app_subtitle)): ?><span class="topbar-brand__subtitle"><?= htmlspecialchars($app_subtitle, ENT_QUOTES) ?></span><?php endif; ?></a>

    <button type="button" class="topbar-nav-toggle" id="topbarNavToggle" aria-label="Menu" aria-expanded="false">☰</button>

    <nav class="topbar-nav" id="topbarNav" aria-label="Navigation principale">
        <?php
        // Un seul mécanisme de rendu pour tous les rôles (Owner/Manager/Employé) :
        // chaque item choisit sa route et son libellé via un ternaire sur $isManager,
        // et les sections purement admin (hr, system, la plupart de statistics)
        // disparaissent d'elles-mêmes pour un employé puisque $routeVisible('admin.xxx')
        // y est déjà false (aucune permission RBAC) — aucune condition de rôle
        // dédiée à écrire pour ça.
        $navHide = fn(string $k): bool => in_array($k, (array)($user_nav_hidden ?? []), true);
        // Visibilité pilotée par la permission réellement déclarée sur la route
        // (route_visible, RBAC-V2) plutôt qu'une clé recopiée à la main à côté du
        // lien — absent sur les pages hors /admin : tout est visible par défaut.
        $routeVisible = $route_visible ?? fn(string $r): bool => true;
        $_defSec = ['planning', 'hr', 'requests', 'statistics', 'system', 'account'];
        $_rawOrd = (array)($user_nav_section_order ?? []);
        $_secOrd = array_values(array_unique(array_merge(
            array_intersect($_rawOrd, $_defSec),
            $_defSec
        )));
        $reportHref = (is_array($managed_store_ids) && count($managed_store_ids) === 1)
            ? $BASE_URL . '/admin/stores/' . $managed_store_ids[0] . '/employee-report'
            : $BASE_URL . '/admin/stores';
        ?>
        <a href="<?= route_url($isManager ? 'home' : 'employee.dashboard') ?>" class="topbar-nav-link<?= ($isManager ? ($path === '/' || $path === '') : $path === '/employee') ? ' topbar-nav-link--active' : '' ?>"><?= __('dashboard') ?></a>
        <?php if (bundle_enabled('team-directory')): ?>
            <a href="<?= route_url('team.index') ?>" class="topbar-nav-link<?= str_starts_with($path, '/team') ? ' topbar-nav-link--active' : '' ?>"><?= __('team_directory') ?></a>
        <?php endif; ?>

        <?php foreach ($_secOrd as $_sec): switch ($_sec):
                case 'planning': ?>
                    <?php
                    $_shiftsRoute     = $isManager ? 'admin.shifts.timeline' : 'employee.shifts.day';
                    $_timeclockRoute  = $isManager ? 'admin.timeclocks' : 'employee.timeclock';
                    $_canShifts       = $feat('shifts') && $routeVisible($_shiftsRoute);
                    $_canTimeclock    = $feat('timeclock') && $routeVisible($_timeclockRoute);
                    $_shiftsActive    = $isManager ? $onShifts : str_starts_with($path, '/employee/shifts');
                    $_timeclockActive = str_starts_with($path, $isManager ? '/admin/timeclocks' : '/employee/timeclock');
                    ?>
                    <?php if (($_canShifts && !$navHide('shifts')) || ($isManager && $_canShifts && !$navHide('calendar')) || ($isManager && $_canShifts && !$navHide('shift_types')) || ($_canTimeclock && !$navHide('timeclocks'))): ?>
                        <div class="topbar-nav-group<?= ($_shiftsActive || $onCalendar || str_starts_with($path, '/admin/shift-types') || $_timeclockActive) ? ' topbar-nav-group--active' : '' ?>">
                            <button type="button" class="topbar-nav-group__trigger"><?= __('planning') ?> <span class="topbar-nav-group__caret">▾</span></button>
                            <div class="topbar-nav-group__panel">
                                <?php if ($_canShifts && !$navHide('shifts')): ?>
                                    <a href="<?= route_url($_shiftsRoute) ?>" class="topbar-nav-group__link<?= $_shiftsActive ? ' topbar-nav-group__link--active' : '' ?>"><?= $ico('calendar') ?><?= $isManager ? __('shifts') : __('my_planning') ?></a>
                                <?php endif; ?>
                                <?php if ($isManager && $_canShifts && !$navHide('calendar')): ?>
                                    <a href="<?= route_url('admin.shifts.calendar') ?>" class="topbar-nav-group__link<?= $onCalendar ? ' topbar-nav-group__link--active' : '' ?>"><?= $ico('calendar') ?><?= __('calendar') ?></a>
                                <?php endif; ?>
                                <?php if ($isManager && $_canShifts && !$navHide('shift_types')): ?>
                                    <a href="<?= route_url('admin.shift_types') ?>" class="topbar-nav-group__link<?= str_starts_with($path, '/admin/shift-types') ? ' topbar-nav-group__link--active' : '' ?>"><?= $ico('tag') ?><?= __('shift_types') ?></a>
                                <?php endif; ?>
                                <?php if ($_canTimeclock && !$navHide('timeclocks')): ?>
                                    <a href="<?= route_url($_timeclockRoute) ?>" class="topbar-nav-group__link<?= $_timeclockActive ? ' topbar-nav-group__link--active' : '' ?>"><?= $ico('clock') ?><?= $isManager ? __('timeclocks') : __('timeclock') ?></a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php break;
                case 'hr': ?>
                    <?php if (($routeVisible('admin.users') && !$navHide('users')) || ($routeVisible('admin.stores') && !$navHide('stores'))): ?>
                        <div class="topbar-nav-group<?= (str_starts_with($path, '/admin/users') || str_starts_with($path, '/admin/stores')) ? ' topbar-nav-group--active' : '' ?>">
                            <button type="button" class="topbar-nav-group__trigger"><?= __('hr') ?> <span class="topbar-nav-group__caret">▾</span></button>
                            <div class="topbar-nav-group__panel">
                                <?php if ($routeVisible('admin.users') && !$navHide('users')): ?>
                                    <a href="<?= route_url('admin.users') ?>" class="topbar-nav-group__link<?= str_starts_with($path, '/admin/users') ? ' topbar-nav-group__link--active' : '' ?>"><?= $ico('users') ?><?= __('staff') ?></a>
                                <?php endif; ?>
                                <?php if ($routeVisible('admin.stores') && !$navHide('stores')): ?>
                                    <a href="<?= route_url('admin.stores') ?>" class="topbar-nav-group__link<?= str_starts_with($path, '/admin/stores') ? ' topbar-nav-group__link--active' : '' ?>"><?= $ico('store') ?><?= __('stores') ?></a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php break;
                case 'requests': ?>
                    <?php
                    $_timeoffRoute     = $isManager ? 'admin.timeoff' : 'employee.timeoff';
                    $_swapsRoute       = $isManager ? 'admin.swap_requests' : 'employee.swaps';
                    $_openShiftsRoute  = $isManager ? 'admin.open_shifts' : 'employee.open_shifts';
                    $_messagesRoute    = $isManager ? 'admin.messages' : 'employee.messages';
                    $_canTimeoff       = $feat('timeoff') && $routeVisible($_timeoffRoute);
                    $_canSwaps         = $feat('swaps') && $routeVisible($_swapsRoute);
                    $_canOpenShifts    = $feat('open_shifts') && $routeVisible($_openShiftsRoute);
                    $_canMessages      = $feat('messages') && $routeVisible($_messagesRoute);
                    $_timeoffActive    = str_starts_with($path, $isManager ? '/admin/timeoff' : '/employee/timeoff');
                    $_swapsActive      = str_starts_with($path, $isManager ? '/admin/swap-requests' : '/employee/swaps');
                    $_openShiftsActive = str_starts_with($path, $isManager ? '/admin/open-shifts' : '/employee/open-shifts');
                    $_messagesActive   = str_starts_with($path, $isManager ? '/admin/messages' : '/employee/messages');
                    ?>
                    <?php if (($_canTimeoff && !$navHide('timeoff')) || ($_canSwaps && !$navHide('swaps')) || ($_canOpenShifts && !$navHide('open_shifts')) || ($_canMessages && !$navHide('messages'))): ?>
                        <div class="topbar-nav-group<?= ($_timeoffActive || $_swapsActive || $_openShiftsActive || $_messagesActive) ? ' topbar-nav-group--active' : '' ?>">
                            <button type="button" class="topbar-nav-group__trigger"><?= __('requests') ?> <span class="topbar-nav-group__caret">▾</span></button>
                            <div class="topbar-nav-group__panel">
                                <?php if ($_canTimeoff && !$navHide('timeoff')): ?>
                                    <a href="<?= route_url($_timeoffRoute) ?>" class="topbar-nav-group__link<?= $_timeoffActive ? ' topbar-nav-group__link--active' : '' ?>"><?= $ico('leaf') ?><?= $isManager ? __('timeoff') : __('my_timeoff') ?></a>
                                <?php endif; ?>
                                <?php if ($_canSwaps && !$navHide('swaps')): ?>
                                    <a href="<?= route_url($_swapsRoute) ?>" class="topbar-nav-group__link<?= $_swapsActive ? ' topbar-nav-group__link--active' : '' ?>"><?= $ico('arrows') ?><?= __('swaps') ?></a>
                                <?php endif; ?>
                                <?php if ($_canOpenShifts && !$navHide('open_shifts')): ?>
                                    <a href="<?= route_url($_openShiftsRoute) ?>" class="topbar-nav-group__link<?= $_openShiftsActive ? ' topbar-nav-group__link--active' : '' ?>"><?= $ico('plus') ?><?= __('open_shifts') ?></a>
                                <?php endif; ?>
                                <?php if ($_canMessages && !$navHide('messages')): ?>
                                    <a href="<?= route_url($_messagesRoute) ?>" class="topbar-nav-group__link<?= $_messagesActive ? ' topbar-nav-group__link--active' : '' ?>">
                                        <?= $ico('message') ?><?= __('messages') ?>
                                        <?php if (($unread_messages_count ?? 0) > 0): ?><span class="nav-badge"><?= (int) $unread_messages_count ?></span><?php endif; ?>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php break;
                case 'statistics': ?>
                    <?php
                    $_canHiring            = bundle_enabled('hiring-report') && $routeVisible('admin.reports.hiring');
                    $_canResignation       = bundle_enabled('resignation-report') && $routeVisible('admin.reports.resignation');
                    $_canSalary            = bundle_enabled('salary-report') && $routeVisible('admin.reports.salary');
                    $_canPhotos            = $feat('photos') && $routeVisible('admin.photos.index');
                    // Repli générique "/admin/stores" absent pour un Owner (qui gère déjà
                    // tous les stores via HR > Stores) : conserve le menu Owner identique
                    // à avant la fusion de ce bloc avec la branche Manager.
                    $_canEmployeeReport    = $isManager && !$isOwner && $routeVisible('admin.stores.employee_report');
                    // daily_reports : un admin/manager a un lien unique (toutes les stores),
                    // un employé peut en avoir plusieurs (un par store où il est staff) — seul
                    // item dont la FORME diffère réellement, pas juste la destination.
                    $_canDailyReportsAdmin    = $isManager && $feat('daily_reports') && $routeVisible('admin.daily_reports.all');
                    $_canDailyReportsEmployee = !$isManager && $feat('daily_reports') && !empty($daily_report_staff_stores);
                    $_canDailyReports         = $_canDailyReportsAdmin || $_canDailyReportsEmployee;
                    ?>
                    <?php if ($_canHiring || ($_canEmployeeReport && !$navHide('employee_report')) || ($_canDailyReports && !$navHide('daily_reports')) || ($_canResignation && !$navHide('resignation_report')) || ($_canSalary && !$navHide('salary_report')) || ($_canPhotos && !$navHide('photos'))): ?>
                        <div class="topbar-nav-group<?= (str_contains($path, '/reports/') || str_contains($path, '/employee-report') || str_contains($path, '/daily-reports') || str_contains($path, '/admin/photos')) ? ' topbar-nav-group--active' : '' ?>">
                            <button type="button" class="topbar-nav-group__trigger"><?= __('reports') ?> <span class="topbar-nav-group__caret">▾</span></button>
                            <div class="topbar-nav-group__panel">
                                <?php if ($_canHiring && !$navHide('hiring_report')): ?>
                                    <a href="<?= route_url('admin.reports.hiring') ?>" class="topbar-nav-group__link<?= str_contains($path, '/reports/hiring') ? ' topbar-nav-group__link--active' : '' ?>"><?= $ico('person') ?><?= __('hiring_reports') ?></a>
                                <?php endif; ?>
                                <?php if ($_canEmployeeReport && !$navHide('employee_report')): ?>
                                    <a href="<?= $reportHref ?>" class="topbar-nav-group__link<?= str_contains($path, '/employee-report') ? ' topbar-nav-group__link--active' : '' ?>"><?= $ico('chart') ?><?= __('employee_report') ?></a>
                                <?php endif; ?>
                                <?php if ($_canDailyReports && !$navHide('daily_reports')): ?>
                                    <?php if ($_canDailyReportsAdmin): ?>
                                        <a href="<?= route_url('admin.daily_reports.all') ?>" class="topbar-nav-group__link<?= str_contains($path, '/daily-reports') ? ' topbar-nav-group__link--active' : '' ?>"><?= $ico('chart') ?><?= __('daily_reports') ?></a>
                                    <?php else: ?>
                                        <?php foreach ($daily_report_staff_stores as $_drStore): ?>
                                            <?php $_drId = (int) $_drStore['id']; ?>
                                            <a href="<?= route_url('admin.stores') ?>/<?= $_drId ?>/daily-reports"
                                                class="topbar-nav-group__link<?= str_contains($path, '/stores/' . $_drId . '/daily-reports') ? ' topbar-nav-group__link--active' : '' ?>">
                                                <?= $ico('chart') ?><?= __('daily_reports') ?><?= count($daily_report_staff_stores) > 1 ? ' — ' . htmlspecialchars($_drStore['name']) : '' ?>
                                            </a>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
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
                <?php break;
                case 'system': ?>
                    <?php if ($isOwner): ?>
                        <div class="topbar-nav-group<?= (str_starts_with($path, '/admin/activity') || str_starts_with($path, '/admin/owner-settings') || str_starts_with($path, '/admin/feedbacks') || str_starts_with($path, '/admin/backup') || str_starts_with($path, '/admin/update') || str_starts_with($path, '/admin/languages') || str_starts_with($path, '/admin/bundles') || str_starts_with($path, '/admin/license')) ? ' topbar-nav-group--active' : '' ?>">
                            <button type="button" class="topbar-nav-group__trigger"><?= __('system') ?> <span class="topbar-nav-group__caret">▾</span></button>
                            <div class="topbar-nav-group__panel">
                                <?php if (!$navHide('audit_log')): ?>
                                    <a href="<?= route_url('admin.activity') ?>" class="topbar-nav-group__link<?= str_starts_with($path, '/admin/activity') ? ' topbar-nav-group__link--active' : '' ?>"><?= $ico('history') ?><?= __('activity_log') ?></a>
                                <?php endif; ?>
                                <a href="<?= route_url('admin.owner_settings') ?>" class="topbar-nav-group__link<?= (str_starts_with($path, '/admin/owner-settings') || str_starts_with($path, '/admin/feedbacks') || str_starts_with($path, '/admin/backup') || str_starts_with($path, '/admin/update') || str_starts_with($path, '/admin/languages') || str_starts_with($path, '/admin/bundles') || str_starts_with($path, '/admin/license')) ? ' topbar-nav-group__link--active' : '' ?>"><?= $ico('gear') ?><?= __('settings') ?></a>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php break;
                case 'account': ?>
                    <?php if (!$navHide('my_profile')): ?>
                        <a href="<?= route_url('profile') ?>" class="topbar-nav-link<?= (str_starts_with($path, '/profile') && ($_GET['tab'] ?? 'info') !== 'nav') ? ' topbar-nav-link--active' : '' ?>"><?= __('my_profile') ?></a>
                    <?php endif; ?>
        <?php break;
            endswitch;
        endforeach; ?>
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

        <?php if (!$isManager && isset($employee_month_stats)): ?>
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
                            <strong class="sb-stats-value--primary"><?= number_format($ems['hours_month'], 1) ?> <?= __('hours_unit') ?></strong>
                        </div>
                        <div class="sb-stats-row">
                            <span class="sb-stats-label"><?= __('avg_per_week') ?></span>
                            <strong><?= number_format($ems['hours_week'], 1) ?> <?= __('hours_unit') ?></strong>
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
                    <div class="notif-dropdown__header-actions">
                        <?php if ($_unreadCount > 0): ?>
                            <form method="POST" action="<?= route_url('notifications.read_all') ?>" class="notif-mark-read-form">
                                <?= csrf_field() ?>
                                <button type="submit" class="btn btn--ghost btn--xs"><?= __('mark_all_read') ?></button>
                            </form>
                        <?php endif; ?>
                        <?php if (!empty($_dropdownItems)): ?>
                            <form method="POST" action="<?= route_url('notifications.delete_all') ?>" class="notif-mark-read-form" onsubmit="return confirm('<?= __('delete_all_notifications_confirm') ?>')">
                                <?= csrf_field() ?>
                                <button type="submit" class="btn btn--ghost btn--xs"><?= __('delete_all_notifications') ?></button>
                            </form>
                        <?php endif; ?>
                    </div>
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
                <?php if (!empty($auth_user['avatar_path'])): ?>
                    <img src="<?= route_url('user.avatar', ['user_id' => (int) $auth_user['id']]) ?>" alt="" class="user-dropdown__avatar">
                <?php else: ?>
                    <span class="user-dropdown__avatar"><?= htmlspecialchars($initials) ?></span>
                <?php endif; ?>
                <span class="user-dropdown__label"><?= $displayName ?></span>
                <span class="user-dropdown__chevron" aria-hidden="true">▾</span>
            </button>

            <div class="user-dropdown__panel" role="menu">
                <div class="user-dropdown__header">
                    <?php if (!empty($auth_user['avatar_path'])): ?>
                        <img src="<?= route_url('user.avatar', ['user_id' => (int) $auth_user['id']]) ?>" alt="" class="user-dropdown__header-avatar">
                    <?php else: ?>
                        <span class="user-dropdown__avatar user-dropdown__header-avatar"><?= htmlspecialchars($initials) ?></span>
                    <?php endif; ?>
                    <div>
                        <div class="user-dropdown__hname"><?= $displayName ?></div>
                        <div class="user-dropdown__hrole"><?= $roleLabel ?></div>
                    </div>
                </div>

                <a href="<?= route_url('profile') ?>" class="user-dropdown__item" role="menuitem">
                    <span class="user-dropdown__item-icon">👤</span><?= __('my_profile') ?>
                </a>

                <hr class="user-dropdown__divider">

                <button id="themeToggle" class="user-dropdown__item" role="menuitem" type="button"
                        data-label-light="<?= __('theme_light_mode') ?>"
                        data-label-dark="<?= __('theme_dark_mode') ?>"
                        data-title-light="<?= __('switch_to_light_mode') ?>"
                        data-title-dark="<?= __('switch_to_dark_mode') ?>">
                    <span class="user-dropdown__item-icon" id="theme-icon">🌙</span>
                    <span id="theme-label"><?= __('theme_dark_mode') ?></span>
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

                <hr class="user-dropdown__divider">

                <a href="<?= $BASE_URL ?>/docs"
                    class="user-dropdown__item<?= str_starts_with($path, '/docs') ? ' topbar-nav-link--active' : '' ?>"
                    role="menuitem">
                    <span class="user-dropdown__item-icon">📖</span><?= __('docs') ?>
                </a>

                <button id="forceRefreshBtn" class="user-dropdown__item" role="menuitem" type="button"
                        data-label-default="<?= __('force_refresh') ?>"
                        data-label-busy="<?= __('force_refresh_busy') ?>">
                    <span class="user-dropdown__item-icon">🔄</span><span id="force-refresh-label"><?= __('force_refresh') ?></span>
                </button>

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