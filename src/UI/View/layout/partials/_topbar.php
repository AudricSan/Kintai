<header class="topbar">
    <button class="sidebar-toggle" id="sidebarToggle" aria-label="Toggle sidebar">☰</button>

    <?php
    $bcSeg = array_values(array_filter(explode('/', trim($path, '/'))));
    $bcSectionAdmin = [
        'shifts'              => __('planning'),
        'shift-types'         => __('planning'),
        'timeclocks'          => __('planning'),
        'users'               => __('hr'),
        'stores'              => __('hr'),
        'timeoff'             => __('requests'),
        'swap-requests'       => __('requests'),
        'open-shifts'         => __('requests'),
        'messages'            => __('requests'),
        'reports'             => __('reports'),
        'daily-reports'       => __('statistics'),
        'store-profitability' => __('statistics'),
        'activity'            => __('system'),
        'feedbacks'           => __('system'),
        'nav-settings'        => __('system'),
        'owner-settings'      => __('system'),
    ];
    $bcSectionEmployee = [
        'shifts'        => __('planning'),
        'timeclock'     => __('planning'),
        'timeoff'       => __('requests'),
        'swaps'         => __('requests'),
        'open-shifts'   => __('requests'),
        'messages'      => __('requests'),
        'profile'       => __('account'),
        'nav-settings'  => __('account'),
    ];
    if (empty($bcSeg) || $path === '/') {
        $bcSection = null;
    } elseif (($bcSeg[0] ?? '') === 'admin') {
        $bcSection = $bcSectionAdmin[$bcSeg[1] ?? ''] ?? null;
    } elseif (($bcSeg[0] ?? '') === 'employee') {
        $bcSection = $bcSectionEmployee[$bcSeg[1] ?? ''] ?? null;
    } else {
        $bcSection = null;
    }
    $bcPage = $title ?? __('dashboard');
    ?>
    <nav class="topbar-breadcrumb" aria-label="breadcrumb">
        <?php if ($bcSection): ?>
            <span class="topbar-breadcrumb__section"><?= htmlspecialchars($bcSection) ?></span>
            <span class="topbar-breadcrumb__sep" aria-hidden="true">›</span>
        <?php endif; ?>
        <span class="topbar-breadcrumb__page"><?= htmlspecialchars($bcPage) ?></span>
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
        'message_received' => __('notif_message_received'),
        'timeoff_approved' => __('notif_timeoff_approved'),
        'timeoff_refused'  => __('notif_timeoff_refused'),
        'swap_accepted'    => __('notif_swap_accepted'),
        'swap_refused'     => __('notif_swap_refused'),
        'shift_assigned'   => __('notif_shift_assigned'),
    ];
    $_notifIcons = [
        'message_received' => '✉',
        'timeoff_approved' => '✓',
        'timeoff_refused'  => '✗',
        'swap_accepted'    => '⇄',
        'swap_refused'     => '⇄',
        'shift_assigned'   => '📅',
    ];
    $_dropdownItems = $notifications_dropdown ?? [];
    $_unreadCount   = (int) ($unread_notifications_count ?? 0);
    $_recentJson    = json_encode(array_map(fn($n) => [
        'title' => $_notifLabels[$n['type'] ?? ''] ?? ($n['type'] ?? ''),
        'body'  => $n['body'] ?? '',
    ], $recent_notifications ?? []), JSON_UNESCAPED_UNICODE);
    ?>

    <div class="topbar-actions">

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
