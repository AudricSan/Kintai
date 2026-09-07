<?php
use kintai\UI\Components\Alert;
use kintai\UI\Components\Badge;
use kintai\UI\Components\Button;
use kintai\UI\Components\Card;
use kintai\UI\Components\Flash;

/**
 * @var array  $user
 * @var string $tab               'info'|'availability'|'ical'|'nav'
 * @var array  $stores            id → store
 * @var int    $store_id
 * @var array  $existing          day_of_week → availability row
 * @var array  $ical_links
 * @var array  $nav_hidden
 * @var array  $nav_section_order
 * @var array  $nav_default_sections
 * @var array  $nav_allowed_keys
 * @var bool   $is_owner
 * @var array  $bottom_nav_pool
 * @var array  $bottom_nav_items
 */

$dayNames = [
    1 => __('monday'),    2 => __('tuesday'),   3 => __('wednesday'),
    4 => __('thursday'),  5 => __('friday'),     6 => __('saturday'),
    7 => __('sunday'),
];
$defaultOpen  = '09:00';
$defaultClose = '18:00';
?>

<div class="page-header">
    <h2 class="page-header__title"><?= __('profile') ?></h2>
</div>

<?php
echo Flash::fromQuery('success', [
    'saved'            => __('save_success'),
    'password_changed' => __('password_changed_success'),
    '1'                => __('operation_success'),
])->render();
$errMsg = match ($_GET['error'] ?? '') {
    'password_mismatch'      => __('password_mismatch'),
    'password_too_short'     => __('password_too_short'),
    'current_password_wrong' => __('current_password_wrong'),
    default                  => '',
};
if ($errMsg !== '') {
    echo Alert::make($errMsg)->danger()->render();
}
?>

<!-- Onglets -->
<div class="profile-tabs card--mb">
    <a href="<?= route_url('profile') ?>?tab=info"
       class="profile-tab<?= $tab === 'info' ? ' profile-tab--active' : '' ?>">
        <?= __('profile_tab_info') ?>
    </a>
    <a href="<?= route_url('profile') ?>?tab=availability"
       class="profile-tab<?= $tab === 'availability' ? ' profile-tab--active' : '' ?>">
        <?= __('profile_tab_availability') ?>
    </a>
    <a href="<?= route_url('profile') ?>?tab=ical"
       class="profile-tab<?= $tab === 'ical' ? ' profile-tab--active' : '' ?>">
        <?= __('profile_tab_ical') ?>
    </a>
    <a href="<?= route_url('profile') ?>?tab=nav"
       class="profile-tab<?= $tab === 'nav' ? ' profile-tab--active' : '' ?>">
        <?= __('profile_tab_nav') ?>
    </a>
    <a href="<?= route_url('profile') ?>?tab=data"
       class="profile-tab<?= $tab === 'data' ? ' profile-tab--active' : '' ?>">
        Confidentialité
    </a>
</div>

<!-- ═══ TAB : INFORMATIONS ═══ -->
<?php if ($tab === 'info'): ?>
<?php
ob_start();
?>
<form method="POST" action="<?= route_url('profile') ?>" class="form-stack">
    <?= csrf_field() ?>
    <div class="form-row">
        <div class="form-group">
            <label class="form-label"><?= __('first_name') ?></label>
            <input type="text" class="form-control form-control--readonly"
                   value="<?= htmlspecialchars($user['first_name'] ?? '') ?>" disabled>
        </div>
        <div class="form-group">
            <label class="form-label"><?= __('last_name') ?></label>
            <input type="text" class="form-control form-control--readonly"
                   value="<?= htmlspecialchars($user['last_name'] ?? '') ?>" disabled>
        </div>
    </div>

    <div class="form-group">
        <label class="form-label"><?= __('email') ?></label>
        <input type="email" class="form-control form-control--readonly"
               value="<?= htmlspecialchars($user['email'] ?? '') ?>" disabled>
    </div>

    <div class="form-group">
        <label class="form-label form-label--required"><?= __('language') ?></label>
        <select name="language" class="form-control">
            <?php foreach (($active_languages ?? []) as $_lang): ?>
            <option value="<?= htmlspecialchars($_lang['code']) ?>" <?= ($user['language'] ?? 'fr') === $_lang['code'] ? 'selected' : '' ?>><?= htmlspecialchars($_lang['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="form-actions">
        <?= Button::make(__('save'))->primary()->submit()->render() ?>
    </div>
</form>
<?php $infoBody = ob_get_clean();
echo Card::make(__('profile_tab_info'))->body($infoBody)->render();
ob_start();
?>
<?php if (empty($user['email'])):
    echo Alert::make(__('profile_no_email_warning'))->warning()->render();
endif; ?>
<form method="POST" action="<?= route_url('profile.password') ?>" class="form-stack">
    <?= csrf_field() ?>
    <div class="form-group">
        <label class="form-label form-label--required"><?= __('current_password') ?></label>
        <input type="password" name="current_password" class="form-control" required>
    </div>
    <div class="form-row">
        <div class="form-group">
            <label class="form-label form-label--required"><?= __('new_password') ?></label>
            <input type="password" name="new_password" class="form-control" minlength="4" required>
        </div>
        <div class="form-group">
            <label class="form-label form-label--required"><?= __('confirm_password') ?></label>
            <input type="password" name="confirm_password" class="form-control" minlength="4" required>
        </div>
    </div>
    <div class="form-actions">
        <?= Button::make(__('change_password'))->primary()->submit()->render() ?>
    </div>
</form>
<?php $pwdBody = ob_get_clean();
echo Card::make(__('change_password'))->body($pwdBody)->render();
?>

<!-- ═══ TAB : DISPONIBILITÉS ═══ -->
<?php elseif ($tab === 'availability'): ?>

<?php if (empty($stores)):
    echo Alert::make(__('no_store_assigned'))->info()->render();
else: ?>

<?php if (count($stores) > 1): ?>
<div class="card card--mb">
    <div class="card-body">
        <form method="GET" action="<?= route_url('profile') ?>" class="form-inline-flex">
            <?= csrf_field() ?>
            <input type="hidden" name="tab" value="availability">
            <label class="form-label" for="store_sel"><?= __('store') ?></label>
            <select id="store_sel" name="store_id" class="form-control" onchange="this.form.submit()">
                <?php foreach ($stores as $sid => $s): ?>
                    <option value="<?= (int) $sid ?>" <?= $sid === $store_id ? 'selected' : '' ?>>
                        <?= htmlspecialchars($s['name'] ?? '#' . $sid) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>
</div>
<?php endif; ?>

<p class="text-muted mb-sm"><?= __('availability_subtitle') ?></p>

<form method="POST" action="<?= route_url('employee.profile.availability') ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="store_id" value="<?= (int) $store_id ?>">
    <div class="card card--mb">
        <div class="table-wrap">
            <table class="data-table avail-table">
                <thead>
                    <tr>
                        <th><?= __('day') ?></th>
                        <th><?= __('available') ?></th>
                        <th><?= __('start') ?></th>
                        <th><?= __('end') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($dayNames as $dow => $label): ?>
                        <?php
                        $row     = $existing[$dow] ?? null;
                        $isAvail = $row === null || (int) ($row['is_available'] ?? 1) === 1;
                        $start   = ($isAvail && $row) ? substr($row['start_time'], 0, 5) : $defaultOpen;
                        $end     = ($isAvail && $row) ? substr($row['end_time'],   0, 5) : $defaultClose;
                        ?>
                        <tr id="avail-row-<?= $dow ?>"<?= !$isAvail ? ' class="avail-row--off"' : '' ?>>
                            <td><?= htmlspecialchars($label) ?></td>
                            <td>
                                <label class="form-toggle">
                                    <input type="checkbox" name="days[<?= $dow ?>][available]" value="1"
                                           class="form-toggle__input" data-dow="<?= $dow ?>"
                                           <?= $isAvail ? 'checked' : '' ?>>
                                    <span class="form-toggle__track"></span>
                                </label>
                            </td>
                            <td>
                                <input type="time" name="days[<?= $dow ?>][start_time]"
                                       value="<?= htmlspecialchars($start) ?>"
                                       class="form-control" id="start-<?= $dow ?>"
                                       <?= !$isAvail ? 'disabled' : '' ?>>
                            </td>
                            <td>
                                <input type="time" name="days[<?= $dow ?>][end_time]"
                                       value="<?= htmlspecialchars($end) ?>"
                                       class="form-control" id="end-<?= $dow ?>"
                                       <?= !$isAvail ? 'disabled' : '' ?>>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <div class="form-actions">
        <?= Button::make(__('save'))->primary()->submit()->render() ?>
    </div>
</form>

<script src="<?= $BASE_URL ?>/assets/js/modules/profile.js"></script>

<?php endif; ?>

<!-- ═══ TAB : MENU ═══ -->
<?php elseif ($tab === 'nav'): ?>

<?php
// Défaut à false (pas à true) : AuthController passe toujours cette valeur explicitement
// (depuis AuthService::isManager(), déjà corrigé — voir CHANGELOG), gardé par cohérence
// défensive avec le même principe appliqué à shifts-timeline.php/shifts-calendar.php.
$_isManagerView = $is_manager_view ?? false;

if ($_isManagerView) {
    $_navFeatMap = [
        'shifts'          => 'shifts',
        'calendar'        => 'shifts',
        'shift_types'     => 'shifts',
        'timeclocks'      => 'timeclock',
        'users'           => null,
        'stores'          => null,
        'timeoff'         => 'timeoff',
        'swaps'           => 'swaps',
        'open_shifts'     => 'open_shifts',
        'messages'        => 'messages',
        'employee_report' => null,
        'daily_reports'   => 'daily_reports',
        'audit_log'       => null,
    ];
    $_navAllSections = [
        'planning'   => ['shifts', 'calendar', 'shift_types', 'timeclocks'],
        'hr'         => ['users', 'stores'],
        'requests'   => ['timeoff', 'swaps', 'open_shifts', 'messages'],
        'statistics' => ['employee_report', 'daily_reports'],
        'system'     => ['audit_log'],
    ];
    $_navSaveAction = route_url('admin.nav_settings');
} else {
    $_navFeatMap = [
        'my_planning'   => 'shifts',
        'timeclock'     => 'timeclock',
        'my_timeoff'    => 'timeoff',
        'swaps'         => 'swaps',
        'open_shifts'   => 'open_shifts',
        'messages'      => 'messages',
        'daily_reports' => 'daily_reports',
        'my_profile'    => null,
    ];
    $_navAllSections = [
        'planning'   => ['my_planning', 'timeclock'],
        'requests'   => ['my_timeoff', 'swaps', 'open_shifts', 'messages'],
        'statistics' => ['daily_reports'],
        'account'    => ['my_profile'],
    ];
    $_navSaveAction = route_url('employee.nav_settings');
}

$_navHasFeat = fn(?string $f): bool =>
    $f === null || ($is_owner ?? false) || $store_features === null || in_array($f, (array) $store_features, true);

$_navSections = [];
foreach ($_navAllSections as $_sk => $_keys) {
    $filtered = array_values(array_filter(
        array_intersect($_keys, $nav_allowed_keys),
        fn(string $k) => $_navHasFeat($_navFeatMap[$k] ?? null)
    ));
    if (!empty($filtered)) {
        $_navSections[$_sk] = $filtered;
    }
}

$_bnFeatMap = $_isManagerView ? [
    'shifts'        => 'shifts',
    'team'          => null,
    'requests'      => 'timeoff',
    'messages'      => 'messages',
    'swaps'         => 'swaps',
    'timeclocks'    => 'timeclock',
    'daily_reports' => 'daily_reports',
] : $_navFeatMap;
$_filteredBnPool = array_values(array_filter(
    $bottom_nav_pool ?? [],
    fn(string $k) => $_navHasFeat($_bnFeatMap[$k] ?? null)
));
?>

<form method="POST" action="<?= $_navSaveAction ?>">
    <?= csrf_field() ?>

    <?php
    ob_start();
    ?>
    <div class="form-inline-flex mb-sm">
        <p class="text-muted flex-1"><?= __('nav_section_order_desc') ?></p>
        <?= Button::make(__('nav_reset_order'))
            ->ghost()->sm()
            ->attrs(['name' => 'reset_section_order', 'value' => '1',
                'onclick' => "return confirm('" . htmlspecialchars(__('nav_reset_order') . ' ?', ENT_QUOTES) . "')"])
            ->submit()->render() ?>
    </div>
    <ul class="nav-order-list" id="navSectionOrder">
        <?php foreach ($nav_section_order as $_sk): if (!isset($_navSections[$_sk])) continue; ?>
            <li class="nav-order-item" draggable="true" data-key="<?= htmlspecialchars($_sk) ?>">
                <span class="nav-order-handle" aria-hidden="true">⠿</span>
                <span class="nav-order-label"><?= __($_sk) ?></span>
                <div class="nav-order-arrows">
                    <button type="button" class="nav-order-btn nav-order-btn--up" aria-label="Monter">▲</button>
                    <button type="button" class="nav-order-btn nav-order-btn--down" aria-label="Descendre">▼</button>
                </div>
                <input type="hidden" name="section_order[]" value="<?= htmlspecialchars($_sk) ?>">
            </li>
        <?php endforeach; ?>
    </ul>
    <?php
    echo Card::make(__('nav_section_order'))->body(ob_get_clean())->render();
    ob_start();
    ?>
    <p class="text-muted mb-sm"><?= __('nav_settings_desc') ?></p>

    <div class="form-group mb-sm">
        <div class="nav-pref-item nav-pref-item--fixed">
            <label class="form-toggle">
                <input type="checkbox" class="form-toggle__input" checked disabled>
                <span class="form-toggle__track"></span>
            </label>
            <span class="nav-pref-label"><?= __('dashboard') ?></span>
            <?= Badge::make(__('nav_always_visible'))->muted()->sm()->render() ?>
        </div>
    </div>

    <?php foreach ($nav_section_order as $_sk): if (!isset($_navSections[$_sk])) continue; ?>
        <div class="nav-pref-section">
            <div class="nav-pref-section-title"><?= __($_sk) ?></div>
            <?php foreach ($_navSections[$_sk] as $_k): ?>
                <div class="nav-pref-item">
                    <label class="form-toggle">
                        <input type="checkbox"
                               name="nav[<?= $_k ?>]"
                               value="1"
                               class="form-toggle__input"
                               <?= !in_array($_k, $nav_hidden, true) ? 'checked' : '' ?>>
                        <span class="form-toggle__track"></span>
                    </label>
                    <span class="nav-pref-label"><?= __($_k) ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endforeach; ?>
    <?php
    echo Card::make(__('nav_items_visibility'))->body(ob_get_clean())->render();
    ?>

    <?php if (!empty($_filteredBnPool)):
    ob_start();
    ?>
    <p class="text-muted mb-sm"><?= __('bottom_nav_config_desc') ?></p>
    <p class="text-muted mb-sm text--sm hidden" id="bottomNavHint"><?= __('bottom_nav_min_max') ?></p>

    <div class="form-group mb-sm">
        <div class="nav-pref-item nav-pref-item--fixed">
            <label class="form-toggle">
                <input type="checkbox" class="form-toggle__input" checked disabled>
                <span class="form-toggle__track"></span>
            </label>
            <span class="nav-pref-label"><?= __('home') ?></span>
            <?= Badge::make(__('nav_always_visible'))->muted()->sm()->render() ?>
        </div>
    </div>

    <?php foreach ($_filteredBnPool as $_bnKey): ?>
        <div class="nav-pref-item">
            <label class="form-toggle">
                <input type="checkbox"
                       name="bottom_nav[]"
                       value="<?= htmlspecialchars($_bnKey) ?>"
                       class="form-toggle__input bottom-nav-check"
                       <?= in_array($_bnKey, $bottom_nav_items ?? [], true) ? 'checked' : '' ?>>
                <span class="form-toggle__track"></span>
            </label>
            <span class="nav-pref-label"><?= __($_bnKey) ?></span>
        </div>
    <?php endforeach; ?>
    <?php
    echo Card::make(__('bottom_nav_config'))->body(ob_get_clean())->render();
    endif; ?>

    <div class="form-actions">
        <?= Button::make(__('save'))->primary()->submit()->render() ?>
    </div>
</form>
<script src="<?= $BASE_URL ?>/assets/js/modules/nav-order.js"></script>
<script src="<?= $BASE_URL ?>/assets/js/modules/bottom-nav-config.js"></script>

<!-- ═══ TAB : CALENDRIER iCal ═══ -->
<?php elseif ($tab === 'ical'): ?>

<?php
ob_start();
?>
<p class="text-muted mb-sm"><?= __('ical_feeds_description') ?></p>
<?php if (empty($ical_links)): ?>
    <p class="text-muted"><?= __('no_store_assigned') ?></p>
<?php else: ?>
    <?php foreach ($ical_links as $link): ?>
    <div class="form-group mb-sm">
        <label class="form-label"><?= htmlspecialchars($link['store_name']) ?></label>
        <div class="form-inline-flex">
            <input type="text" class="form-control" readonly
                   value="<?= htmlspecialchars($link['url']) ?>"
                   onclick="this.select()">
            <button type="button" class="btn btn--ghost btn--sm"
                    data-copy-url="<?= htmlspecialchars($link['url']) ?>"
                    onclick="navigator.clipboard.writeText(this.dataset.copyUrl);this.textContent='✓'">
                <?= __('ical_copy') ?>
            </button>
            <form method="POST"
                  action="<?= $BASE_URL ?>/ical/<?= (int) $link['store_id'] ?>/regenerate"
                  onsubmit="return confirm('<?= htmlspecialchars(__('ical_regenerate_confirm'), ENT_QUOTES) ?>')">
                <?= csrf_field() ?>
                <?= Button::make(__('ical_regenerate'))->ghost()->sm()->submit()->render() ?>
            </form>
        </div>
    </div>
    <?php endforeach; ?>
<?php endif; ?>
<?php
echo Card::make(__('ical_feeds'))->body(ob_get_clean())->render();
?>

<!-- ═══ TAB : CONFIDENTIALITÉ & DONNÉES ═══ -->
<?php elseif ($tab === 'data'): ?>

<?php
ob_start();
echo '<p class="mb-sm">Conformément au RGPD, vous pouvez télécharger l\'ensemble de vos données personnelles au format JSON.</p>';
echo Button::make('Télécharger mes données')->primary()->link(route_url('profile.export'))->render();
echo Card::make('Export de vos données')->body(ob_get_clean())->render();

ob_start();
echo Alert::make('Attention : la suppression de votre compte est irréversible. Vos données personnelles seront anonymisées, mais les données liées (pointages, shifts) seront conservées pour l\'intégrité des archives.')->danger()->render();
?>
<form method="POST" action="<?= route_url('profile.delete') ?>" class="form-stack">
    <?= csrf_field() ?>
    <div class="form-group mw-420">
        <label class="form-label form-label--required" for="delete_password">
            Confirmez avec votre mot de passe actuel
        </label>
        <input type="password" id="delete_password" name="password"
               class="form-control" required minlength="4">
    </div>
    <?= Button::make('Supprimer mon compte')
        ->danger()
        ->attrs(['onclick' => "return confirm('Êtes-vous sûr de vouloir supprimer définitivement votre compte ?')"])
        ->submit()->render() ?>
</form>
<?php
echo Card::make('Suppression du compte')->body(ob_get_clean())->render();

echo Card::make('Politique de confidentialité')
    ->body('<p>Consultez notre politique de confidentialité pour comprendre comment vos données sont collectées et traitées.</p><a href="' . route_url('privacy') . '" class="btn btn--ghost">Voir la politique</a>')
    ->render();
?>

<?php endif; ?>
