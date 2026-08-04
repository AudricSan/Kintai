<?php
use kintai\UI\Components\Badge;
use kintai\UI\Components\Button;
use kintai\UI\Components\Card;
use kintai\UI\Components\Flash;

/**
 * @var string $mode
 * @var array  $user
 * @var array  $all_stores
 * @var array  $user_memberships
 * @var array  $available_stores
 * @var array  $user_shift_types
 * @var array  $user_rates
 * @var array  $stores_map
 */
$user_shift_types      ??= [];
$user_rates            ??= [];
$stores_map            ??= [];
$type_store_names      ??= [];
$user_memberships      ??= [];
$available_stores      ??= [];
$all_stores            ??= [];
$assignable_roles      ??= [];
$default_store_role_id ??= 0;
$action = $mode === 'edit'
    ? $BASE_URL . '/admin/users/' . (int) $user['id'] . '/edit'
    : route_url('admin.users.create');

echo Flash::fromQuery('success', [
    'updated'        => __('user_updated'),
    'rate_updated'   => __('user_rate_updated'),
    'rate_deleted'   => __('user_rate_deleted'),
    'password_reset' => __('password_reset_success'),
])->render();
echo Flash::fromQuery('error', [
    'email_taken'        => __('email_taken'),
    'name_required'      => __('name_required'),
    'furigana_required'  => __('furigana_required'),
])->render();
?>
<div class="page-header">
    <h2 class="page-header__title"><?= $mode === 'edit' ? __('edit_user') : __('new_user') ?></h2>
    <div class="page-header__actions">
        <a href="<?= route_url('admin.users') ?>" class="btn btn--ghost">← <?= __('back') ?></a>
    </div>
</div>

<form method="POST" action="<?= htmlspecialchars($action) ?>" class="form-stack" novalidate>
    <?= csrf_field() ?>
    <?php $as_cards = true; include __DIR__ . '/../_partials/_form-user.php'; ?>
    <p class="form-error" data-required-error hidden><?= __('required_fields_missing') ?></p>
    <div class="form-actions">
        <?= Button::make($mode === 'edit' ? __('save') : __('new_user'))->primary()->submit()->render() ?>
        <a href="<?= route_url('admin.users') ?>" class="btn btn--ghost"><?= __('cancel') ?></a>
    </div>
</form>
<?php if ($mode === 'edit'): ?>
<form method="POST" action="<?= $BASE_URL ?>/admin/users/<?= (int) $user['id'] ?>/reset-password" id="resetPasswordForm">
    <?= csrf_field() ?>
</form>
<?php endif; ?>
<script src="<?= $BASE_URL ?>/assets/js/modules/required-fields-feedback.js"></script>

<?php if ($mode === 'edit'): ?>
<div class="card card--mt">
    <div class="card-header card-header--flex"><h3 class="card-title"><?= __('stores_plural') ?></h3></div>
    <div class="table-wrap">
        <table class="data-table" data-mob-stack>
            <thead><tr><th><?= __('store') ?></th><th><?= __('role') ?></th><th></th></tr></thead>
            <tbody>
                <?php if (empty($user_memberships)): ?>
                    <tr><td colspan="3" class="td-center td-muted"><?= __('no_store_assigned') ?></td></tr>
                <?php else: ?>
                    <?php foreach ($user_memberships as $m): ?>
                        <tr>
                            <td data-label="<?= htmlspecialchars(__('store')) ?>"><?= htmlspecialchars($m['store_name'] ?? '') ?></td>
                            <td data-label="<?= htmlspecialchars(__('role')) ?>"><?= Badge::make(htmlspecialchars($m['role_name'] ?? ($m['role'] ?? '—')))->variant(!empty($m['role_is_managing']) ? 'warning' : 'active')->render() ?></td>
                            <td>
                                <form method="POST" action="<?= $BASE_URL ?>/admin/stores/<?= (int)$m['store_id'] ?>/members/<?= (int)$m['id'] ?>/delete" class="form-inline" onsubmit="return confirm('<?= __('confirm_remove_member') ?>')">
                                    <?= csrf_field() ?>
                                    <?= Button::make(__('remove'))->ghost()->sm()->submit()->render() ?>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php if (!empty($available_stores)): ?>
    <div class="card-body card-body--border-top">
        <p class="section-subtitle"><?= __('assign_to_store') ?></p>
        <form method="POST" action="<?= $BASE_URL ?>/admin/stores/0/members" id="addStoreForm" class="form-flex">
            <?= csrf_field() ?>
            <input type="hidden" name="user_id" value="<?= (int)$user['id'] ?>">
            <input type="hidden" name="redirect_to" value="<?= $BASE_URL ?>/admin/users/<?= (int)$user['id'] ?>/edit?success=member_added">
            <div class="form-group form-group--200">
                <label class="form-label"><?= __('store') ?></label>
                <select name="store_id_select" class="form-control" required onchange="document.getElementById('addStoreForm').action='<?= $BASE_URL ?>/admin/stores/' + this.value + '/members'">
                    <option value="">— <?= __('select') ?> —</option>
                    <?php foreach ($available_stores as $s): ?>
                        <option value="<?= (int)$s['id'] ?>"><?= htmlspecialchars($s['name'] ?? '') ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group form-group--160">
                <label class="form-label"><?= __('role') ?></label>
                <select name="role_id" class="form-control">
                    <?php foreach ($assignable_roles as $r): ?>
                        <option value="<?= (int) $r['id'] ?>" <?= (int) $r['id'] === (int) $default_store_role_id ? 'selected' : '' ?>><?= htmlspecialchars($r['name'] ?? '') ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?= Button::make(__('add'))->primary()->sm()->submit()->render() ?>
        </form>
    </div>
    <?php endif; ?>
</div>

<?php if (!empty($user_memberships)): ?>
<div class="card card--mt">
    <div class="card-header"><span><?= __('social_deductions') ?></span></div>
    <?php foreach ($user_memberships as $m):
        $sid = (int) $m['store_id'];
        $mid = (int) $m['id'];
        $ov = $m['ded_overrides'] ?? [];
        $storeEnabled = !empty($m['store_ded_settings']['enabled']);
        $subject = !empty($ov['subject_to_deductions']);
    ?>
    <div class="ded-store-block">
        <div class="ded-store-title">
            <?= htmlspecialchars($m['store_name'] ?? '') ?>
            <?php if (!$storeEnabled): ?><?= Badge::make(__('deductions_disabled'))->variant('muted')->render() ?><?php endif; ?>
        </div>
        <form method="POST" action="<?= $BASE_URL ?>/admin/stores/<?= $sid ?>/members/<?= $mid ?>/deductions">
            <?= csrf_field() ?>
            <input type="hidden" name="_redirect_to" value="<?= htmlspecialchars($BASE_URL . '/admin/users/' . (int) $user['id'] . '/edit?success=deductions_saved') ?>">
            <div class="ded-check-row check-label">
                <label class="form-toggle">
                    <input type="checkbox" name="subject_to_deductions" value="1" class="form-toggle__input" <?= $subject ? 'checked' : '' ?> <?= !$storeEnabled ? 'disabled' : '' ?>>
                    <span class="form-toggle__track"></span>
                </label>
                <span><?= __('subject_to_deductions') ?></span>
            </div>
            <div class="ded-save-row">
                <?= Button::make(__('save'))->primary()->sm()->submit()->disabled(!$storeEnabled)->render() ?>
            </div>
        </form>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php if (!empty($user_shift_types)): ?>
<div class="card card--mt">
    <div class="card-header"><span><?= __('custom_hourly_rates') ?><span class="text-sm text-dim"> <?= __('default_rate_hint') ?></span></span></div>
    <div class="table-wrap">
        <table class="data-table" data-mob-stack>
            <thead><tr><th><?= __('shift_types') ?></th><th><?= __('store') ?></th><th><?= __('base_rate') ?></th><th><?= __('custom_rate') ?></th><th><?= __('actions') ?></th></tr></thead>
            <tbody>
                <?php foreach ($user_shift_types as $t):
                    $tid = (int) $t['id'];
                    $currentRate = $user_rates[$tid] ?? null;
                ?>
                <tr>
                    <td data-label="<?= htmlspecialchars(__('shift_types')) ?>"><strong><?= htmlspecialchars($t['name'] ?? '') ?></strong><span class="text-sm-muted"> (<?= htmlspecialchars($t['code'] ?? '') ?>)</span></td>
                    <td data-label="<?= htmlspecialchars(__('store')) ?>" class="text-sm td-muted"><?= htmlspecialchars(implode(', ', $type_store_names[$tid] ?? [])) ?: '—' ?></td>
                    <td data-label="<?= htmlspecialchars(__('base_rate')) ?>" class="text-sm"><?= $t['hourly_rate'] !== null ? number_format((float) $t['hourly_rate'], 2, '.', '') : '—' ?></td>
                    <td data-label="<?= htmlspecialchars(__('custom_rate')) ?>"><?= $currentRate !== null ? Badge::make(number_format((float) $currentRate['hourly_rate'], 2, '.', ''))->active()->render() : '<span class="text-sm text-muted">—</span>' ?></td>
                    <td data-label="<?= htmlspecialchars(__('actions')) ?>">
                        <div class="btn-group">
                            <form method="POST" action="<?= $BASE_URL ?>/admin/users/<?= (int) $user['id'] ?>/rates" class="form-inline-flex">
                                <?= csrf_field() ?>
                                <input type="hidden" name="shift_type_id" value="<?= $tid ?>">
                                <input type="number" name="hourly_rate" class="form-control form-control-sm w-90" min="0" step="0.01"
                                       value="<?= $currentRate !== null ? number_format((float) $currentRate['hourly_rate'], 2, '.', '') : '' ?>" placeholder="0.00">
                                <?= Button::make(__('apply'))->ghost()->sm()->submit()->render() ?>
                            </form>
                            <?php if ($currentRate !== null): ?>
                            <form method="POST" action="<?= $BASE_URL ?>/admin/users/<?= (int) $user['id'] ?>/rates/<?= (int) $currentRate['id'] ?>/delete" class="form-inline">
                                <?= csrf_field() ?>
                                <?= Button::make('✕')->ghost()->sm()->attrs(['onclick' => "return confirm('" . __('confirm_delete_custom_rate') . "')", 'title' => __('reset_to_default_rate')])->submit()->render() ?>
                            </form>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<div class="card card--mt">
    <div class="card-header"><span><?= __('reports') ?></span></div>
    <div class="card-body">
        <?php if (!empty($user_memberships)): ?>
            <?php foreach ($user_memberships as $m): ?>
                <?php $sid = (int) $m['store_id']; ?>
                <div class="btn-group mb-sm">
                    <a href="<?= $BASE_URL ?>/admin/stores/<?= $sid ?>/reports/resignation/create?user_id=<?= (int)$user['id'] ?>" class="btn btn--danger btn--sm"><?= __('resign') ?> — <?= htmlspecialchars($m['store_name'] ?? '') ?></a>
                    <a href="<?= $BASE_URL ?>/admin/stores/<?= $sid ?>/reports/salary/create?user_id=<?= (int)$user['id'] ?>" class="btn btn--ghost btn--sm"><?= __('salary_report') ?> — <?= htmlspecialchars($m['store_name'] ?? '') ?></a>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p class="text-dim"><?= __('no_store_assigned') ?></p>
        <?php endif; ?>
    </div>
</div>
<?php endif; // mode === 'edit' (ligne 57) ?>
<script src="<?= $BASE_URL ?>/assets/js/modules/user-form-live-check.js"></script>
<script src="<?= $BASE_URL ?>/assets/js/modules/furigana-suggest.js"></script>
<script src="<?= $BASE_URL ?>/assets/js/modules/user-role-select.js"></script>
