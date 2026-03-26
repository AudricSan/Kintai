<?php
/** @var string $mode              'create'|'edit' */
/** @var array  $user */
/** @var array  $all_stores        stores disponibles pour l'affectation */
/** @var array  $user_memberships  appartenance aux stores (mode edit) */
/** @var array  $available_stores  stores non encore affectés (mode edit) */
/** @var array  $user_shift_types  types de shift des stores de l'utilisateur (mode edit) */
/** @var array  $user_rates        map shift_type_id → rate row (mode edit) */
/** @var array  $stores_map        map store_id → store name (mode edit) */
$user_shift_types ??= [];
$user_rates       ??= [];
$stores_map       ??= [];
$user_memberships ??= [];
$available_stores ??= [];
$all_stores       ??= [];
$roles = ['staff' => __('staff'), 'manager' => 'Manager', 'admin' => __('admin')];
$action = $mode === 'edit'
    ? $BASE_URL . '/admin/users/' . (int) $user['id'] . '/edit'
    : $BASE_URL . '/admin/users/create';
?>

<?php if ($flash = ($_GET['success'] ?? '')): ?>
    <div class="alert alert--success">
        <?= match($flash) {
            'updated'      => __('user_updated'),
            'rate_updated' => __('user_rate_updated'),
            'rate_deleted' => __('user_rate_deleted'),
            default        => __('operation_success'),
        } ?>
    </div>
<?php endif; ?>

<div class="page-header">
    <h2 class="page-header__title"><?= $mode === 'edit' ? __('edit_user') : __('new_user') ?></h2>
    <div class="page-header__actions">
        <a href="<?= $BASE_URL ?>/admin/users" class="btn btn--ghost">← <?= __('back') ?></a>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <form method="POST" action="<?= htmlspecialchars($action) ?>">
            <div class="form-stack">

                <div class="form-group">
                    <label class="form-label form-label--required"><?= __('display_name') ?></label>
                    <input type="text" name="display_name" class="form-control"
                           value="<?= htmlspecialchars($user['display_name'] ?? '') ?>" required>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label"><?= __('first_name') ?></label>
                        <input type="text" name="first_name" class="form-control"
                               value="<?= htmlspecialchars($user['first_name'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label"><?= __('last_name') ?></label>
                        <input type="text" name="last_name" class="form-control"
                               value="<?= htmlspecialchars($user['last_name'] ?? '') ?>">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label form-label--required"><?= __('email') ?></label>
                        <input type="email" name="email" class="form-control"
                               value="<?= htmlspecialchars($user['email'] ?? '') ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label"><?= __('phone') ?></label>
                        <input type="tel" name="phone" class="form-control"
                               value="<?= htmlspecialchars($user['phone'] ?? '') ?>"
                               placeholder="+33 6 00 00 00 00">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label"><?= __('employee_code') ?> <span class="text-hint">(<?= __('employee_code_hint') ?>)</span></label>
                    <input type="text" name="employee_code" class="form-control input-code mw-200"
                           value="<?= htmlspecialchars($user['employee_code'] ?? '') ?>"
                           placeholder="ex : EMP001">
                    <p class="form-hint">
                        <?= __('employee_login_hint') ?>
                    </p>
                </div>

                <div class="form-group">
                    <label class="form-label <?= $mode === 'create' ? 'form-label--required' : '' ?>">
                        <?= __('password') ?> <?= $mode === 'edit' ? __('password_edit_hint') : '' ?>
                    </label>
                    <input type="password" name="password" class="form-control"
                           <?= $mode === 'create' ? 'required' : '' ?> autocomplete="new-password"
                           placeholder="<?= $mode === 'create' ? '' : __('password_edit_hint') ?>">
                    <?php if ($mode === 'create'): ?>
                        <p class="form-hint"><?= __('password_create_hint') ?></p>
                    <?php endif; ?>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label"><?= __('identification_color') ?></label>
                        <div class="input-group">
                            <input type="color" name="color" class="form-control input-color"
                                   value="<?= htmlspecialchars($user['color'] ?? '#3B82F6') ?>">
                            <span class="text-sm-muted"><?= __('planning_visible_hint') ?></span>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label"><?= __('global_role') ?></label>
                        <select name="is_admin" class="form-control">
                            <option value="0" <?= empty($user['is_admin']) ? 'selected' : '' ?>><?= __('staff') ?></option>
                            <option value="1" <?= !empty($user['is_admin']) ? 'selected' : '' ?>><?= __('admin') ?></option>
                        </select>
                    </div>
                    <?php if ($mode === 'edit'): ?>
                    <div class="form-group">
                        <label class="form-label"><?= __('status') ?></label>
                        <select name="is_active" class="form-control">
                            <option value="1" <?= !empty($user['is_active']) ? 'selected' : '' ?>><?= __('active') ?></option>
                            <option value="0" <?= empty($user['is_active']) ? 'selected' : '' ?>><?= __('inactive') ?></option>
                        </select>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- ── Affectation store ──────────────────────────── -->
                <?php if ($mode === 'create'): ?>
                <div class="section-divider">
                    <h4 class="section-title"><?= __('assign_to_store') ?></h4>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label"><?= __('store') ?></label>
                            <select name="store_id" class="form-control">
                                <option value="">— <?= __('none') ?> —</option>
                                <?php foreach ($all_stores as $s): ?>
                                    <option value="<?= (int)$s['id'] ?>"><?= htmlspecialchars($s['name'] ?? '') ?> (<?= htmlspecialchars($s['code'] ?? '') ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label"><?= __('store_role') ?></label>
                            <select name="store_role" class="form-control">
                                <?php foreach ($roles as $val => $label): ?>
                                    <option value="<?= $val ?>" <?= $val === 'staff' ? 'selected' : '' ?>><?= $label ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <div class="form-actions">
                    <button type="submit" class="btn btn--primary">
                        <?= $mode === 'edit' ? __('save') : __('new_user') ?>
                    </button>
                    <a href="<?= $BASE_URL ?>/admin/users" class="btn btn--ghost"><?= __('cancel') ?></a>
                </div>

            </div>
        </form>
    </div>
</div>

<!-- ── Magasins (mode edit) ────────────────────────────────────────── -->
<?php if ($mode === 'edit'): ?>
<div class="card card--mt">
    <div class="card-header card-header--flex">
        <h3 class="card-title"><?= __('stores_plural') ?></h3>
    </div>
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr><th><?= __('store') ?></th><th><?= __('role') ?></th><th></th></tr>
            </thead>
            <tbody>
                <?php if (empty($user_memberships)): ?>
                    <tr><td colspan="3" class="td-center td-muted"><?= __('no_store_assigned') ?></td></tr>
                <?php else: ?>
                    <?php foreach ($user_memberships as $m): ?>
                        <tr>
                            <td><?= htmlspecialchars($m['store_name'] ?? '') ?></td>
                            <td><span class="badge"><?= htmlspecialchars($roles[$m['role'] ?? ''] ?? ($m['role'] ?? '—')) ?></span></td>
                            <td>
                                <form method="POST" action="<?= $BASE_URL ?>/admin/stores/<?= (int)$m['store_id'] ?>/members/<?= (int)$m['id'] ?>/delete" class="form-inline" onsubmit="return confirm('<?= __('confirm_remove_member') ?>')">
                                    <button type="submit" class="btn btn--ghost btn--sm"><?= __('remove') ?></button>
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
                <select name="role" class="form-control">
                    <?php foreach ($roles as $val => $label): ?>
                        <option value="<?= $val ?>" <?= $val === 'staff' ? 'selected' : '' ?>><?= $label ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn btn--primary btn--sm"><?= __('add') ?></button>
        </form>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php if ($mode === 'edit' && !empty($user_memberships)): ?>
<div class="card card--mt">
    <div class="card-header">
        <span><?= __('social_deductions') ?></span>
    </div>

    <?php foreach ($user_memberships as $m):
        $sid          = (int) $m['store_id'];
        $mid          = (int) $m['id'];
        $ov           = $m['ded_overrides'] ?? [];
        $storeEnabled = !empty($m['store_ded_settings']['enabled']);
        $subject      = !empty($ov['subject_to_deductions']);
    ?>
    <div class="ded-store-block">
        <div class="ded-store-title">
            <?= htmlspecialchars($m['store_name'] ?? '') ?>
            <?php if (!$storeEnabled): ?>
                <span class="badge badge--muted"><?= __('deductions_disabled') ?></span>
            <?php endif; ?>
        </div>
        <form method="POST"
              action="<?= $BASE_URL ?>/admin/stores/<?= $sid ?>/members/<?= $mid ?>/deductions">
            <input type="hidden" name="_redirect_to"
                   value="<?= htmlspecialchars($BASE_URL . '/admin/users/' . (int) $user['id'] . '/edit?success=deductions_saved') ?>">
            <div class="form-group form-group--inline">
                <label class="form-check">
                    <input type="checkbox" name="subject_to_deductions" value="1"
                           <?= $subject ? 'checked' : '' ?>
                           <?= !$storeEnabled ? 'disabled' : '' ?>>
                    <span><?= __('subject_to_deductions') ?></span>
                </label>
            </div>
            <div class="ded-save-row">
                <button type="submit" class="btn btn--primary btn--sm"
                        <?= !$storeEnabled ? 'disabled' : '' ?>><?= __('save') ?></button>
            </div>
        </form>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php if ($mode === 'edit' && !empty($user_shift_types)): ?>
<div class="card card--mt">
    <div class="card-header">
        <span><?= __('custom_hourly_rates') ?>
            <span class="text-sm text-dim">
                <?= __('default_rate_hint') ?>
            </span>
        </span>
    </div>

    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th><?= __('shift_types') ?></th>
                    <th><?= __('store') ?></th>
                    <th><?= __('base_rate') ?></th>
                    <th><?= __('custom_rate') ?></th>
                    <th><?= __('actions') ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($user_shift_types as $t): ?>
                    <?php
                    $tid         = (int) $t['id'];
                    $currentRate = $user_rates[$tid] ?? null;
                    ?>
                    <tr>
                        <td>
                            <strong><?= htmlspecialchars($t['name'] ?? '') ?></strong>
                            <span class="text-sm-muted"> (<?= htmlspecialchars($t['code'] ?? '') ?>)</span>
                        </td>
                        <td class="text-sm td-muted">
                            <?= htmlspecialchars($stores_map[(int) $t['store_id']] ?? '#' . $t['store_id']) ?>
                        </td>
                        <td class="text-sm">
                            <?= $t['hourly_rate'] !== null ? number_format((float) $t['hourly_rate'], 2, '.', '') : '—' ?>
                        </td>
                        <td>
                            <?php if ($currentRate !== null): ?>
                                <span class="badge badge--active"><?= number_format((float) $currentRate['hourly_rate'], 2, '.', '') ?></span>
                            <?php else: ?>
                                <span class="text-sm text-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="btn-group">
                                <!-- Définir / modifier le taux -->
                                <form method="POST"
                                      action="<?= $BASE_URL ?>/admin/users/<?= (int) $user['id'] ?>/rates"
                                      class="form-inline-flex">
                                    <input type="hidden" name="shift_type_id" value="<?= $tid ?>">
                                    <input type="number" name="hourly_rate" class="form-control form-control-sm w-90"
                                           min="0" step="0.01"
                                           value="<?= $currentRate !== null ? number_format((float) $currentRate['hourly_rate'], 2, '.', '') : '' ?>"
                                           placeholder="0.00">
                                    <button type="submit" class="btn btn--ghost btn--sm"><?= __('apply') ?></button>
                                </form>
                                <!-- Supprimer le taux personnalisé -->
                                <?php if ($currentRate !== null): ?>
                                <form method="POST"
                                      action="<?= $BASE_URL ?>/admin/users/<?= (int) $user['id'] ?>/rates/<?= (int) $currentRate['id'] ?>/delete"
                                      class="form-inline">
                                    <button type="submit" class="btn btn--ghost btn--sm"
                                            onclick="return confirm('<?= __('confirm_delete_custom_rate') ?>')"
                                            title="<?= __('reset_to_default_rate') ?>">✕</button>
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
