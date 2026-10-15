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
        // Navigation construite à partir de données (kintai\Core\Extension\Navigation) : entrées du Core, entrées
        // déclarées par les bundles actifs (Bundle::navigation(), contrat 1.1) et, en repli, celles des bundles qui
        // ne déclarent encore rien. Un seul rendu pour tous les rôles : chaque entrée choisit sa route selon
        // $isManager, et n'apparaît que si la permission de cette route est accordée (route_visible, RBAC-V2), si la
        // fonctionnalité du magasin est active et si l'utilisateur ne l'a pas masquée dans ses préférences.
        $routeVisible = $route_visible ?? fn(string $r): bool => true;
        $_defSec = ['planning', 'hr', 'requests', 'statistics', 'system'];
        $_rawOrd = (array)($user_nav_section_order ?? []);
        $_secOrd = array_values(array_unique(array_merge(
            array_intersect($_rawOrd, $_defSec),
            $_defSec
        )));
        $_secLabels = ['planning' => 'planning', 'hr' => 'hr', 'requests' => 'requests', 'statistics' => 'reports', 'system' => 'system'];
        $_navSections = \kintai\Core\Extension\Navigation::visible(
            \kintai\Core\Extension\Navigation::current()->items(),
            [
                'isManager'    => $isManager,
                'path'         => $path,
                'routeVisible' => $routeVisible,
                'feat'         => $feat,
                'hidden'       => (array)($user_nav_hidden ?? []),
                'routeUrl'     => fn(string $r): string => route_url($r),
                'translate'    => fn(string $k): string => __($k),
                'view'         => get_defined_vars(),
            ]
        );
        ?>
        <a href="<?= route_url($isManager ? 'home' : 'employee.dashboard') ?>" class="topbar-nav-link<?= ($isManager ? ($path === '/' || $path === '') : $path === '/employee') ? ' topbar-nav-link--active' : '' ?>"><?= __('dashboard') ?></a>
        <?php foreach ($_navSections['main'] ?? [] as $_entry): foreach ($_entry['links'] as $_link): ?>
            <a href="<?= htmlspecialchars($_link['href']) ?>" class="topbar-nav-link<?= $_link['active'] ? ' topbar-nav-link--active' : '' ?>"><?= htmlspecialchars($_link['label']) ?></a>
        <?php endforeach; endforeach; ?>

        <?php foreach ($_secOrd as $_sec):
            $_entries = $_navSections[$_sec] ?? [];
            if ($_entries === []) continue;
            $_groupActive = false;
            foreach ($_entries as $_entry) {
                foreach ($_entry['links'] as $_link) {
                    $_groupActive = $_groupActive || $_link['active'];
                }
            }
            ?>
            <div class="topbar-nav-group<?= $_groupActive ? ' topbar-nav-group--active' : '' ?>">
                <button type="button" class="topbar-nav-group__trigger"><?= __($_secLabels[$_sec]) ?> <span class="topbar-nav-group__caret">▾</span></button>
                <div class="topbar-nav-group__panel">
                    <?php foreach ($_entries as $_entry): foreach ($_entry['links'] as $_link): ?>
                        <a href="<?= htmlspecialchars($_link['href']) ?>" class="topbar-nav-group__link<?= $_link['active'] ? ' topbar-nav-group__link--active' : '' ?>"><?= $ico($_entry['icon']) ?><?= htmlspecialchars($_link['label']) ?><?php if ($_entry['badge'] > 0): ?><span class="nav-badge"><?= (int) $_entry['badge'] ?></span><?php endif; ?></a>
                    <?php endforeach; endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
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

    $_dropdownItems = $notifications_dropdown ?? [];
    $_unreadCount   = (int) ($unread_notifications_count ?? 0);
    $_recentJson    = json_encode(array_map(fn($n) => [
        'id'    => (int) ($n['id'] ?? 0),
        'title' => notification_type_label($n['type'] ?? ''),
        'body'  => $n['body'] ?? '',
        'link'  => route_url('notifications.open', ['id' => (int) ($n['id'] ?? 0)]),
    ], $recent_notifications ?? []), JSON_UNESCAPED_UNICODE);
    ?>

    <div class="topbar-actions">

        <?php // Gaté par la donnée elle-même (calculée par AuthMiddleware pour tout
        // non-Owner, Manager compris), pas par le rôle : un Manager qui a lui-même
        // des shifts/un taux horaire doit voir ses propres stats comme n'importe
        // quel employé, pas en être exclu parce qu'il gère aussi un store. ?>
        <?php if (isset($employee_month_stats)): ?>
            <?php $ems = $employee_month_stats;
            $emsCur = $ems['currency'] ?? 'JPY';
            $emsStyle = $ems['currency_symbol_style'] ?? 'kanji'; ?>
            <div class="topbar-nav-group topbar-nav-group--stats">
                <button type="button" class="topbar-nav-group__trigger topbar-nav-group__trigger--stats">
                    <?= $ico('clock') ?><?= number_format($ems['hours_month'], 1) ?> h <span class="topbar-nav-group__caret">▾</span>
                </button>
                <div class="topbar-nav-group__panel topbar-nav-group__panel--stats">
                    <div class="sb-month-nav">
                        <button data-on-click="sbMonthNav" data-args="<?= htmlspecialchars(json_encode([$ems['prev_month']]), ENT_QUOTES) ?>" class="sb-month-btn">&larr;</button>
                        <span class="sb-month-label"><?= htmlspecialchars($ems['month_label']) ?></span>
                        <button data-on-click="sbMonthNav" data-args="<?= htmlspecialchars(json_encode([$ems['next_month']]), ENT_QUOTES) ?>" class="sb-month-btn" <?= $ems['is_current'] ? 'disabled' : '' ?>>&rarr;</button>
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
                            <button data-on-click="sbDetailOpen" class="sb-detail-btn">
                                <?= __('see_details') ?> (<?= count($ems['shift_details']) ?> shift<?= count($ems['shift_details']) > 1 ? 's' : '' ?>) &#9654;
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <?php if (!empty($ems['shift_details'])): ?>
                <div id="sb-detail-overlay" data-on-click="sbDetailClose">
                    <div class="sb-modal" data-stop-propagation>
                        <div class="sb-modal-header">
                            <strong><?= __('shift_detail_title', ['month' => htmlspecialchars($ems['month_label'])]) ?></strong>
                            <button data-on-click="sbDetailClose" class="sb-modal-close">&times;</button>
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
                            <button data-on-click="sbDetailClose" class="btn btn--ghost btn--sm"><?= __('close') ?></button>
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
                            <form method="POST" action="<?= route_url('notifications.delete_all') ?>" class="notif-mark-read-form" data-confirm="<?= htmlspecialchars(__('delete_all_notifications_confirm'), ENT_QUOTES) ?>">
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
                        <a href="<?= route_url('notifications.open', ['id' => (int) $_n['id']]) ?>"
                           class="notif-entry<?= $_nRead ? '' : ' notif-entry--unread' ?>">
                            <span class="notif-entry__icon"><?= notification_type_icon($_n['type'] ?? '') ?></span>
                            <div class="notif-entry__body">
                                <div class="notif-entry__title"><?= htmlspecialchars(notification_type_label($_n['type'] ?? '')) ?></div>
                                <div class="notif-entry__text"><?= htmlspecialchars($_n['body'] ?? '') ?></div>
                                <div class="notif-entry__time"><?= htmlspecialchars(substr($_n['created_at'] ?? '', 0, 16)) ?></div>
                            </div>
                        </a>
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