<?php
use kintai\UI\Components\Button;
use kintai\UI\Components\Flash;

/** @var string $mode                 'create'|'edit' */
/** @var array  $role                 Données du rôle */
/** @var array  $permission_categories Catégorie => [actions] (PermissionCatalog::CATEGORIES) */
/** @var array  $action_label_keys    Action => clé de traduction */
/** @var array  $granted_permissions  Clés de permission déjà accordées (mode edit) */
/** @var array  $holders              ['assignment_id','user_name','scope_label'][] (mode edit) */
$mode ??= 'create';
$isSystem = !empty($role['is_system']);

echo Flash::fromQuery('error', [
    'invalid_name' => __('role_invalid_name'),
])->render();
?>

<div class="page-header">
    <h2 class="page-header__title"><?= $mode === 'create' ? __('new_role') : htmlspecialchars($role['name'] ?? '') ?></h2>
    <a href="<?= route_url('admin.roles') ?>" class="btn btn--ghost">← <?= __('roles') ?></a>
</div>

<form method="POST" action="<?= $mode === 'create' ? route_url('admin.roles.store') : route_url('admin.roles.update', ['id' => (int) $role['id']]) ?>">
    <?= csrf_field() ?>

    <div class="card card--mb">
        <div class="card-body form-stack">
            <?php if ($isSystem): ?>
                <p class="form-hint"><?= __('system_role') ?></p>
            <?php endif; ?>

            <div class="form-row">
                <?php if (!$isSystem): ?>
                <div class="form-group">
                    <label class="form-label form-label--required"><?= __('name') ?></label>
                    <input type="text" name="name" class="form-control"
                           value="<?= htmlspecialchars($role['name'] ?? '') ?>" required>
                </div>
                <?php endif; ?>
                <div class="form-group">
                    <label class="form-label"><?= __('color') ?></label>
                    <input type="color" name="color" class="input-color"
                           value="<?= htmlspecialchars($role['color'] ?? '#6366f1') ?>"
                           <?= $isSystem ? 'disabled' : '' ?>>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label"><?= __('description') ?></label>
                <textarea name="description" class="form-control" rows="2"
                          <?= $isSystem ? 'disabled' : '' ?>><?= htmlspecialchars($role['description'] ?? '') ?></textarea>
            </div>
        </div>
    </div>

    <?php if (!$isSystem): ?>
    <div class="card card--mb">
        <div class="card-body form-stack">
            <h4 class="section-title"><?= __('permissions') ?></h4>
            <div class="perm-grid" data-perm-grid
                 data-select-all-label="<?= htmlspecialchars(__('select_all')) ?>"
                 data-deselect-all-label="<?= htmlspecialchars(__('deselect_all')) ?>">
                <?php foreach ($permission_categories as $category => $actions): ?>
                <?php
                    $catGranted = 0;
                    foreach ($actions as $action) {
                        if (in_array($category . '.' . $action, $granted_permissions, true)) {
                            $catGranted++;
                        }
                    }
                ?>
                <div class="perm-card" data-perm-card>
                    <div class="perm-card__header">
                        <span class="perm-card__title"><?= __('perm_category_' . $category) ?></span>
                        <div class="perm-card__tools">
                            <span class="perm-card__count" data-perm-count><?= $catGranted ?>/<?= count($actions) ?></span>
                            <button type="button" class="perm-card__toggle-all" data-perm-toggle-all><?= $catGranted === count($actions) ? __('deselect_all') : __('select_all') ?></button>
                        </div>
                    </div>
                    <div class="perm-card__chips">
                        <?php foreach ($actions as $action): ?>
                        <?php
                            $key       = $category . '.' . $action;
                            $fieldName = 'perm_' . str_replace('.', '_', $key);
                            $labelKey  = $action_label_keys[$action] ?? $action;
                            $checked   = in_array($key, $granted_permissions, true);
                        ?>
                        <label class="perm-chip<?= $checked ? ' perm-chip--checked' : '' ?>">
                            <input type="checkbox" name="<?= htmlspecialchars($fieldName) ?>" value="1" data-perm-checkbox
                                   <?= $checked ? 'checked' : '' ?>>
                            <?= __($labelKey) ?>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($mode === 'edit'): ?>
    <div class="card card--mb">
        <div class="card-body">
            <h4 class="section-title"><?= __('role_holders') ?></h4>
            <?php if (empty($holders)): ?>
                <p class="form-hint"><?= __('none') ?></p>
            <?php else: ?>
                <ul class="role-holders-list">
                    <?php foreach ($holders as $h): ?>
                        <li><?= htmlspecialchars($h['user_name']) ?> — <span class="text-sm-muted"><?= htmlspecialchars($h['scope_label']) ?></span></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <?php if (!$isSystem): ?>
    <div class="form-actions">
        <?= Button::make(__('save'))->primary()->submit()->render() ?>
        <a href="<?= route_url('admin.roles') ?>" class="btn btn--ghost"><?= __('cancel') ?></a>
    </div>
    <?php endif; ?>
</form>

<?php if (!$isSystem): ?>
<script src="<?= $BASE_URL ?>/assets/js/modules/permission-editor.js"></script>
<?php endif; ?>
