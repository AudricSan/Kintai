<!DOCTYPE html>
<html lang="<?= \kintai\Core\Container::getInstance()->make(\kintai\Core\Services\TranslationService::class)->getLocale() ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title ?? 'Kintai') ?> — Kintai</title>
    <link rel="stylesheet" href="<?= $BASE_URL ?>/assets/css/app.css">
</head>

<body>
    <div class="app-layout">
        <?php

        use kintai\Core\Container;

        $uri = '/' . trim(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), '/');
        $base = rtrim($BASE_URL, '/');
        $path = $base !== '' && str_starts_with($uri, $base)
            ? substr($uri, strlen($base))
            : $uri;
        $path = '/' . trim($path, '/') ?: '/';
        $isOwner    = !empty($auth_user['is_admin']);
        // managed_store_ids : null = admin global, array = manager restreint
        $isManager  = $isOwner || isset($managed_store_ids);
        // Mode de vue : 'admin' (défaut) ou 'employee' (basculé par le manager/admin)
        $viewMode       = $view_mode ?? 'admin';
        $showAdminMenu  = $isManager && $viewMode === 'admin';
        ?>
        <nav class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <h1 class="sidebar-brand">Kintai</h1>
            </div>
            <ul class="sidebar-nav">
                <?php
                // Détection de l'état actif pour les vues shifts admin
                $onShifts         = str_starts_with($path, '/admin/shifts')
                    && !str_contains($path, 'shift-type');
                $onShiftsTimeline = str_starts_with($path, '/admin/shifts/timeline');
                $onCalendar       = str_starts_with($path, '/admin/shifts/calendar');
                ?>
                <?php if ($showAdminMenu && $isOwner): ?>
                    <!-- Navigation Admin global -->
                    <li><a href="<?= $BASE_URL ?>/" class="sidebar-link<?= ($path === '/' || $path === '') ? ' active' : '' ?>"><?= __('dashboard') ?></a></li>

                    <li class="sidebar-section"><?= __('planning') ?></li>
                    <li><a href="<?= $BASE_URL ?>/admin/shifts/timeline" class="sidebar-link<?= $onShifts ? ' active' : '' ?>"><?= __('shifts') ?></a></li>
                    <li><a href="<?= $BASE_URL ?>/admin/shift-types" class="sidebar-link<?= str_starts_with($path, '/admin/shift-types') ? ' active' : '' ?>"><?= __('shift_types') ?></a></li>

                    <li class="sidebar-section"><?= __('hr') ?></li>
                    <li><a href="<?= $BASE_URL ?>/admin/users" class="sidebar-link<?= str_starts_with($path, '/admin/users') ? ' active' : '' ?>"><?= __('users') ?></a></li>
                    <li><a href="<?= $BASE_URL ?>/admin/stores" class="sidebar-link<?= str_starts_with($path, '/admin/stores') ? ' active' : '' ?>"><?= __('stores') ?></a></li>
                    <li><a href="<?= $BASE_URL ?>/admin/stores" class="sidebar-link<?= str_contains($path, '/employee-report') ? ' active' : '' ?>"><?= __('employee_report') ?></a></li>

                    <li class="sidebar-section"><?= __('requests') ?></li>
                    <li><a href="<?= $BASE_URL ?>/admin/timeoff" class="sidebar-link<?= str_starts_with($path, '/admin/timeoff') ? ' active' : '' ?>"><?= __('timeoff') ?></a></li>
                    <li><a href="<?= $BASE_URL ?>/admin/swap-requests" class="sidebar-link<?= str_starts_with($path, '/admin/swap-requests') ? ' active' : '' ?>"><?= __('swaps') ?></a></li>

                    <li class="sidebar-section"><?= __('system') ?></li>
                    <li><a href="<?= $BASE_URL ?>/admin/audit-log" class="sidebar-link<?= str_starts_with($path, '/admin/audit-log') ? ' active' : '' ?>"><?= __('audit_log') ?></a></li>
                    <li><a href="<?= $BASE_URL ?>/admin/feedbacks" class="sidebar-link<?= str_starts_with($path, '/admin/feedbacks') ? ' active' : '' ?>"><?= __('feedbacks') ?></a></li>

                <?php elseif ($showAdminMenu): ?>
                    <!-- Navigation Manager -->
                    <li class="sidebar-section"><?= __('planning') ?></li>
                    <li><a href="<?= $BASE_URL ?>/admin/shifts/timeline" class="sidebar-link<?= $onShifts ? ' active' : '' ?>"><?= __('shifts') ?></a></li>
                    <li><a href="<?= $BASE_URL ?>/admin/shift-types" class="sidebar-link<?= str_starts_with($path, '/admin/shift-types') ? ' active' : '' ?>"><?= __('shift_types') ?></a></li>

                    <li class="sidebar-section"><?= __('hr') ?></li>
                    <li><a href="<?= $BASE_URL ?>/admin/users" class="sidebar-link<?= str_starts_with($path, '/admin/users') ? ' active' : '' ?>"><?= __('staff') ?></a></li>
                    <li><a href="<?= $BASE_URL ?>/admin/stores" class="sidebar-link<?= str_starts_with($path, '/admin/stores') ? ' active' : '' ?>"><?= __('stores') ?></a></li>

                    <li class="sidebar-section"><?= __('requests') ?></li>
                    <li><a href="<?= $BASE_URL ?>/admin/timeoff" class="sidebar-link<?= str_starts_with($path, '/admin/timeoff') ? ' active' : '' ?>"><?= __('timeoff') ?></a></li>
                    <li><a href="<?= $BASE_URL ?>/admin/swap-requests" class="sidebar-link<?= str_starts_with($path, '/admin/swap-requests') ? ' active' : '' ?>"><?= __('swaps') ?></a></li>

                    <li class="sidebar-section"><?= __('statistics') ?></li>
                    <?php
                    // Un seul store géré → lien direct ; plusieurs → liste des stores
                    $reportHref = (is_array($managed_store_ids) && count($managed_store_ids) === 1)
                        ? $BASE_URL . '/admin/stores/' . $managed_store_ids[0] . '/employee-report'
                        : $BASE_URL . '/admin/stores';
                    ?>
                    <li><a href="<?= $reportHref ?>" class="sidebar-link<?= str_contains($path, '/employee-report') ? ' active' : '' ?>"><?= __('employee_report') ?></a></li>

                <?php else: ?>
                    <!-- Navigation Employee -->
                    <li><a href="<?= $BASE_URL ?>/employee" class="sidebar-link<?= $path === '/employee' ? ' active' : '' ?>"><?= __('dashboard') ?></a></li>

                    <li class="sidebar-section"><?= __('planning') ?></li>
                    <li><a href="<?= $BASE_URL ?>/employee/shifts/day" class="sidebar-link<?= str_starts_with($path, '/employee/shifts') ? ' active' : '' ?>"><?= __('my_planning') ?></a></li>

                    <li class="sidebar-section"><?= __('requests') ?></li>
                    <li><a href="<?= $BASE_URL ?>/employee/timeoff" class="sidebar-link<?= str_starts_with($path, '/employee/timeoff') ? ' active' : '' ?>"><?= __('my_timeoff') ?></a></li>
                    <li><a href="<?= $BASE_URL ?>/employee/swaps" class="sidebar-link<?= str_starts_with($path, '/employee/swaps') ? ' active' : '' ?>"><?= __('swaps') ?></a></li>
                <?php endif; ?>
            </ul>

            <?php if (!$showAdminMenu && isset($employee_month_stats)): ?>
                <?php $ems = $employee_month_stats;
                $emsCur = $ems['currency'] ?? 'JPY'; ?>
                <!-- Bloc heures / salaire sidebar -->
                <div class="sb-stats">

                    <!-- Navigation mois -->
                    <div class="sb-month-nav">
                        <button onclick="sbMonthNav('<?= htmlspecialchars($ems['prev_month']) ?>')"
                            class="sb-month-btn">←</button>
                        <span class="sb-month-label"><?= htmlspecialchars($ems['month_label']) ?></span>
                        <button onclick="sbMonthNav('<?= htmlspecialchars($ems['next_month']) ?>')"
                            class="sb-month-btn"
                            <?= $ems['is_current'] ? 'disabled' : '' ?>>→</button>
                    </div>

                    <!-- Stats compactes -->
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
                                <?= __('see_details') ?> (<?= count($ems['shift_details']) ?> shift<?= count($ems['shift_details']) > 1 ? 's' : '' ?>) ▶
                            </button>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Modal détail sidebar (fixed overlay) -->
                <?php if (!empty($ems['shift_details'])): ?>
                    <div id="sb-detail-overlay" onclick="sbDetailClose()">
                        <div class="sb-modal" onclick="event.stopPropagation()">

                            <!-- En-tête modal -->
                            <div class="sb-modal-header">
                                <strong><?= __('shift_detail_title', ['month' => htmlspecialchars($ems['month_label'])]) ?></strong>
                                <button onclick="sbDetailClose()" class="sb-modal-close">×</button>
                            </div>

                            <!-- Tableau scrollable -->
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
                                                <td class="td-mono"><?= htmlspecialchars($row['start']) ?>–<?= htmlspecialchars($row['end']) ?></td>
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
                                    <!-- Totaux -->
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

                            <!-- Pied modal -->
                            <div class="sb-modal-footer">
                                <button onclick="sbDetailClose()" class="btn btn--ghost btn--sm"><?= __('close') ?></button>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
                <script>
                    function sbMonthNav(month) {
                        var url = new URL(window.location.href);
                        url.searchParams.set('month', month);
                        window.location.href = url.toString();
                    }

                    function sbDetailOpen() {
                        document.getElementById('sb-detail-overlay').classList.add('open');
                    }

                    function sbDetailClose() {
                        document.getElementById('sb-detail-overlay').classList.remove('open');
                    }
                    document.addEventListener('keydown', function(e) {
                        if (e.key === 'Escape') sbDetailClose();
                    });
                </script>
            <?php endif; ?>
        </nav>

        <main class="main-content">
            <header class="topbar">
                <button class="sidebar-toggle" id="sidebarToggle" aria-label="Toggle sidebar">☰</button>
                <span class="topbar-title"><?= htmlspecialchars($title ?? 'Dashboard') ?></span>
                <div class="topbar-right">
                    <!-- Language switcher -->
                    <?php $_locale = \kintai\Core\Container::getInstance()->make(\kintai\Core\Services\TranslationService::class)->getLocale(); ?>
                    <div class="lang-switcher">
                        <a href="<?= $BASE_URL ?>/lang/fr" class="<?= $_locale === 'fr' ? 'active' : '' ?>">FR</a>
                        |
                        <a href="<?= $BASE_URL ?>/lang/en" class="<?= $_locale === 'en' ? 'active' : '' ?>">EN</a>
                        |
                        <a href="<?= $BASE_URL ?>/lang/ja" class="<?= $_locale === 'ja' ? 'active' : '' ?>">JA</a>
                    </div>

                    <?php
                    $displayName = htmlspecialchars(
                        $auth_user['display_name']
                            ?? trim(($auth_user['first_name'] ?? '') . ' ' . ($auth_user['last_name'] ?? ''))
                            ?: ($auth_user['email'] ?? __('user'))
                    );
                    $roleLabel = $isOwner ? __('role_owner') : ($isManager ? __('role_manager') : __('role_employee'));
                    ?>
                    <a href="<?= $BASE_URL ?>/profile" class="topbar-user" title="<?= $roleLabel ?>">
                        <?= $displayName ?>
                        <small>(<?= $roleLabel ?>)</small>
                    </a>
                    <?php if ($auth_is_manager ?? $isManager): ?>
                        <form method="POST" action="<?= $BASE_URL ?>/switch-view">
                            <button type="submit" class="btn btn--ghost btn--sm" title="<?= $viewMode === 'admin' ? __('view_employee') : __('view_admin') ?>">
                                <?= $viewMode === 'admin' ? __('view_employee') : __('view_admin') ?>
                            </button>
                        </form>
                    <?php endif; ?>
                    <form method="POST" action="<?= $BASE_URL ?>/switch-device">
                        <input type="hidden" name="device_view" value="mobile">
                        <button type="submit" class="btn btn--ghost btn--sm" title="<?= __('switch_to_mobile') ?>">📱</button>
                    </form>
                    <form method="POST" action="<?= $BASE_URL ?>/logout">
                        <button type="submit" class="btn btn--ghost btn--sm"><?= __('logout') ?></button>
                    </form>
                </div>
            </header>

            <div class="page-content">
                <?= $content ?>
            </div>
        </main>
    </div>

    <script src="<?= $BASE_URL ?>/assets/js/app.js"></script>

    <?php if (!$showAdminMenu): ?>
        <?php include __DIR__ . '/partials/feedback-modal.php'; ?>
    <?php endif; ?>
</body>

</html>