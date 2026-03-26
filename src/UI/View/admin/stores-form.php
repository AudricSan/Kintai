<?php
/** @var string $mode       'create'|'edit' */
/** @var array  $store */
/** @var array  $members    membres enrichis (mode edit) */
/** @var array  $available  utilisateurs non membres (mode edit) */
/** @var array  $roles      map role → label */
$members   ??= [];
$available ??= [];
$roles     ??= [];
$action  = $mode === 'edit'
    ? $BASE_URL . '/admin/stores/' . (int) $store['id'] . '/edit'
    : $BASE_URL . '/admin/stores/create';
$storeId = (int) ($store['id'] ?? 0);

// Paramètres d'import Excel (avec valeurs par défaut)
$excelDefaults = \kintai\Core\Services\ExcelShiftImport\ExcelShiftImportService::DEFAULTS;
$excelSettings = array_merge(
    $excelDefaults,
    json_decode($store['excel_import_settings'] ?? '{}', true) ?: []
);

$roleBadge = [
    'admin'   => 'badge--danger',
    'manager' => 'badge--warning',
    'staff'   => 'badge--active',
];
?>

<?php if ($flash = ($_GET['success'] ?? '')): ?>
    <div class="alert alert--success">
        <?= match($flash) {
            'updated'        => __('store_updated'),
            'member_added'   => __('member_added'),
            'role_updated'   => __('role_updated'),
            'member_removed' => __('member_removed'),
            default          => __('operation_success'),
        } ?>
    </div>
<?php endif; ?>

<div class="page-header">
    <h2 class="page-header__title"><?= $mode === 'edit' ? __('edit_store') : __('new_store') ?></h2>
    <div class="page-header__actions">
        <a href="<?= $BASE_URL ?>/admin/stores" class="btn btn--ghost">← <?= __('back') ?></a>
    </div>
</div>

<!-- Informations du store -->
<div class="card">
    <div class="card-body">
        <form method="POST" action="<?= htmlspecialchars($action) ?>">
            <div class="form-stack">

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label form-label--required"><?= __('code') ?></label>
                        <input type="text" name="code" class="form-control" maxlength="20"
                               value="<?= htmlspecialchars($store['code'] ?? '') ?>"
                               placeholder="ex: HQ, STORE01" required>
                        <span class="form-hint"><?= __('code') ?> court unique, sera mis en majuscules.</span>
                    </div>
                    <div class="form-group">
                        <label class="form-label form-label--required"><?= __('name') ?></label>
                        <input type="text" name="name" class="form-control"
                               value="<?= htmlspecialchars($store['name'] ?? '') ?>" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label"><?= __('type') ?></label>
                        <input type="text" name="type" class="form-control"
                               value="<?= htmlspecialchars($store['type'] ?? '') ?>"
                               placeholder="ex: retail, warehouse">
                    </div>
                    <div class="form-group">
                        <label class="form-label form-label--required"><?= __('timezone') ?></label>
                        <input type="text" name="timezone" class="form-control"
                               value="<?= htmlspecialchars($store['timezone'] ?? 'UTC') ?>"
                               placeholder="ex: Europe/Paris, Asia/Tokyo" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label"><?= __('currency') ?> (code ISO)</label>
                        <input type="text" name="currency" class="form-control" maxlength="3"
                               value="<?= htmlspecialchars($store['currency'] ?? 'USD') ?>"
                               placeholder="EUR, USD, JPY">
                    </div>
                    <div class="form-group">
                        <label class="form-label"><?= __('locale') ?></label>
                        <input type="text" name="locale" class="form-control" maxlength="10"
                               value="<?= htmlspecialchars($store['locale'] ?? 'en') ?>"
                               placeholder="fr, en, ja">
                    </div>
                </div>

                <!-- Contact -->
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label"><?= __('phone') ?></label>
                        <input type="tel" name="phone" class="form-control"
                               value="<?= htmlspecialchars($store['phone'] ?? '') ?>"
                               placeholder="+33 1 00 00 00 00">
                    </div>
                    <div class="form-group">
                        <label class="form-label"><?= __('email') ?></label>
                        <input type="email" name="email" class="form-control"
                               value="<?= htmlspecialchars($store['email'] ?? '') ?>"
                               placeholder="contact@magasin.com">
                    </div>
                </div>

                <!-- Adresse -->
                <div class="form-group">
                    <label class="form-label"><?= __('address') ?></label>
                    <input type="text" name="address_street" class="form-control"
                           value="<?= htmlspecialchars($store['address_street'] ?? '') ?>"
                           placeholder="<?= __('street_placeholder') ?>">
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label"><?= __('postal_code') ?></label>
                        <input type="text" name="address_postal" class="form-control" maxlength="20"
                               value="<?= htmlspecialchars($store['address_postal'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label"><?= __('city') ?></label>
                        <input type="text" name="address_city" class="form-control"
                               value="<?= htmlspecialchars($store['address_city'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label"><?= __('country') ?></label>
                        <input type="text" name="address_country" class="form-control" maxlength="100"
                               value="<?= htmlspecialchars($store['address_country'] ?? '') ?>"
                               placeholder="France">
                    </div>
                </div>

                <?php if ($mode === 'edit'): ?>
                <div class="form-group">
                    <label class="form-label"><?= __('status') ?></label>
                    <select name="is_active" class="form-control">
                        <option value="1" <?= !empty($store['is_active']) ? 'selected' : '' ?>><?= __('active') ?></option>
                        <option value="0" <?= empty($store['is_active']) ? 'selected' : '' ?>><?= __('inactive') ?></option>
                    </select>
                </div>
                <?php endif; ?>

                <!-- Cotisations sociales -->
                <?php
                $ded = $deductionSettings ?? [];
                $dedEnabled       = !empty($ded['enabled']);
                $dedHealth        = $ded['health_insurance_rate'] ?? 4.99;
                $dedPension       = $ded['pension_rate'] ?? 9.15;
                $dedEmployment    = $ded['employment_insurance_rate'] ?? 0.60;
                $dedIncomeTax     = $ded['income_tax_rate'] ?? 0;
                $dedResidentTax   = $ded['resident_tax_monthly'] ?? 0;
                ?>
                <hr class="section-hr">
                <h3 class="section-h3"><?= __('deduction_settings') ?></h3>

                <div class="form-group form-group--inline">
                    <label class="form-check">
                        <input type="checkbox" name="ded_enabled" value="1" <?= $dedEnabled ? 'checked' : '' ?>>
                        <span><?= __('deductions_enabled') ?></span>
                    </label>
                </div>

                <div class="form-row" id="ded-fields" <?= $dedEnabled ? '' : 'hidden' ?>>
                    <div class="form-col">
                        <label class="form-label"><?= __('ded_health_insurance') ?> (%)</label>
                        <input type="number" name="ded_health_rate" class="form-control" step="0.01" min="0" max="100"
                               value="<?= htmlspecialchars((string) $dedHealth) ?>">
                        <span class="form-hint"><?= __('ded_rate_hint') ?></span>
                    </div>
                    <div class="form-col">
                        <label class="form-label"><?= __('ded_pension') ?> (%)</label>
                        <input type="number" name="ded_pension_rate" class="form-control" step="0.01" min="0" max="100"
                               value="<?= htmlspecialchars((string) $dedPension) ?>">
                    </div>
                    <div class="form-col">
                        <label class="form-label"><?= __('ded_employment_insurance') ?> (%)</label>
                        <input type="number" name="ded_employment_rate" class="form-control" step="0.01" min="0" max="100"
                               value="<?= htmlspecialchars((string) $dedEmployment) ?>">
                    </div>
                    <div class="form-col">
                        <label class="form-label"><?= __('ded_income_tax') ?> (%)</label>
                        <input type="number" name="ded_income_tax_rate" class="form-control" step="0.01" min="0" max="100"
                               value="<?= htmlspecialchars((string) $dedIncomeTax) ?>">
                        <span class="form-hint"><?= __('ded_income_tax_hint') ?></span>
                    </div>
                    <div class="form-col">
                        <label class="form-label"><?= __('ded_resident_tax') ?> (<?= __('monthly_fixed') ?>)</label>
                        <input type="number" name="ded_resident_tax" class="form-control" step="1" min="0"
                               value="<?= htmlspecialchars((string) $dedResidentTax) ?>">
                        <span class="form-hint"><?= __('ded_resident_tax_hint') ?></span>
                    </div>
                </div>

                <script>
                document.querySelector('[name="ded_enabled"]').addEventListener('change', function () {
                    document.getElementById('ded-fields').hidden = !this.checked;
                });
                </script>

                <!-- Paramètres d'import Excel -->
                <hr class="section-hr">
                <h3 class="section-h3"><?= __('excel_import_settings') ?></h3>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label"><?= __('excel_col_start') ?> (col_start)</label>
                        <input type="number" name="excel_col_start" class="form-control" min="1"
                               value="<?= (int) $excelSettings['col_start'] ?>">
                        <span class="form-hint"><?= __('excel_col_start_hint') ?></span>
                    </div>
                    <div class="form-group">
                        <label class="form-label"><?= __('excel_col_end') ?> (col_end)</label>
                        <input type="number" name="excel_col_end" class="form-control" min="1"
                               value="<?= (int) $excelSettings['col_end'] ?>">
                        <span class="form-hint"><?= __('excel_col_end_hint') ?></span>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label"><?= __('excel_base_hour') ?> (base_hour)</label>
                        <input type="number" name="excel_base_hour" class="form-control" min="0" max="23"
                               value="<?= (int) $excelSettings['base_hour'] ?>">
                        <span class="form-hint"><?= __('excel_base_hour_hint') ?></span>
                    </div>
                    <div class="form-group">
                        <label class="form-label"><?= __('excel_minutes_per_col') ?> (minutes_per_col)</label>
                        <input type="number" name="excel_minutes_per_col" class="form-control" min="1"
                               value="<?= (int) $excelSettings['minutes_per_col'] ?>">
                        <span class="form-hint"><?= __('excel_minutes_per_col_hint') ?></span>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label"><?= __('excel_block_size') ?> (block_size)</label>
                        <input type="number" name="excel_block_size" class="form-control" min="2"
                               value="<?= (int) $excelSettings['block_size'] ?>">
                        <span class="form-hint"><?= __('excel_block_size_hint') ?></span>
                    </div>
                    <div class="form-group">
                        <label class="form-label"><?= __('excel_shift_rows') ?> (shift_rows)</label>
                        <input type="number" name="excel_shift_rows" class="form-control" min="1"
                               value="<?= (int) $excelSettings['shift_rows'] ?>">
                        <span class="form-hint"><?= __('excel_shift_rows_hint') ?></span>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label"><?= __('excel_sheet_filter_pattern') ?> (sheet_filter_pattern)</label>
                        <input type="text" name="excel_sheet_filter_pattern" class="form-control"
                               value="<?= htmlspecialchars($excelSettings['sheet_filter_pattern']) ?>">
                        <span class="form-hint"><?= __('excel_sheet_filter_pattern_hint') ?></span>
                    </div>
                </div>

                <hr class="section-hr">
                <h3 class="section-h3"><?= __('shift_settings') ?></h3>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label"><?= __('min_shift_duration') ?></label>
                        <input type="number" name="min_shift_minutes" class="form-control" min="0"
                               value="<?= (int) ($store['min_shift_minutes'] ?? 120) ?>">
                        <span class="form-hint"><?= __('min_shift_minutes_hint') ?></span>
                    </div>
                    <div class="form-group">
                        <label class="form-label"><?= __('max_shift_duration') ?></label>
                        <input type="number" name="max_shift_minutes" class="form-control" min="0"
                               value="<?= (int) ($store['max_shift_minutes'] ?? 480) ?>">
                        <span class="form-hint"><?= __('max_shift_minutes_hint') ?></span>
                    </div>
                    <div class="form-group">
                        <label class="form-label"><?= __('min_staff_per_day') ?></label>
                        <input type="number" name="min_staff_per_day" class="form-control" min="0"
                               value="<?= (int) ($store['min_staff_per_day'] ?? 0) ?>">
                        <span class="form-hint"><?= __('min_staff_per_day_hint') ?></span>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group form-group--full">
                        <label class="form-label"><?= __('low_staff_hours') ?></label>
                        <div class="store-hour-row">
                            <div>
                                <span class="form-hint form-hint--label"><?= __('low_staff_hour_start') ?></span>
                                <input type="number" name="low_staff_hour_start" class="form-control store-hour-input" min="-1" max="23"
                                       value="<?= (int) ($store['low_staff_hour_start'] ?? -1) ?>">
                            </div>
                            <span class="store-hour-sep">→</span>
                            <div>
                                <span class="form-hint form-hint--label"><?= __('low_staff_hour_end') ?></span>
                                <input type="number" name="low_staff_hour_end" class="form-control store-hour-input" min="-1" max="23"
                                       value="<?= (int) ($store['low_staff_hour_end'] ?? -1) ?>">
                            </div>
                        </div>
                        <span class="form-hint"><?= __('low_staff_hours_hint') ?></span>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label"><?= __('auto_pause_threshold') ?></label>
                        <input type="number" name="auto_pause_after_minutes" class="form-control" min="0"
                               value="<?= (int) ($excelSettings['auto_pause_after_minutes'] ?? 0) ?>">
                        <span class="form-hint"><?= __('auto_pause_threshold_hint') ?></span>
                    </div>
                    <div class="form-group">
                        <label class="form-label"><?= __('auto_pause_duration') ?></label>
                        <input type="number" name="auto_pause_minutes" class="form-control" min="1"
                               value="<?= (int) ($excelSettings['auto_pause_minutes'] ?? 30) ?>">
                        <span class="form-hint"><?= __('auto_pause_duration_hint') ?></span>
                    </div>
                </div>


                <div class="form-actions">
                    <button type="submit" class="btn btn--primary">
                        <?= $mode === 'edit' ? __('save') : __('new_store') ?>
                    </button>
                    <a href="<?= $BASE_URL ?>/admin/stores" class="btn btn--ghost"><?= __('cancel') ?></a>
                </div>

            </div>
        </form>
    </div>
</div>

<?php if ($mode === 'edit'): ?>

<!-- Membres du store -->
<div class="card card--mt">
    <div class="card-header">
        <span><?= __('members') ?> <span class="text-sm text-dim">(<?= count($members) ?>)</span></span>
    </div>

    <?php if (empty($members)): ?>
        <div class="empty-state"><?= __('no_store_found') ?></div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th><?= __('name') ?></th>
                        <th><?= __('email') ?></th>
                        <th><?= __('role') ?></th>
                        <th><?= __('status') ?></th>
                        <th><?= __('actions') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($members as $m): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($m['user_name']) ?></strong></td>
                            <td class="text-sm td-muted"><?= htmlspecialchars($m['user_email']) ?></td>
                            <td>
                                <?php $currentRole = $m['role'] ?? 'staff'; ?>
                                <span class="badge <?= $roleBadge[$currentRole] ?? 'badge--active' ?>">
                                    <?= htmlspecialchars($roles[$currentRole] ?? $currentRole) ?>
                                </span>
                            </td>
                            <td>
                                <?php if (!empty($m['is_active'])): ?>
                                    <span class="badge badge--active"><?= __('active') ?></span>
                                <?php else: ?>
                                    <span class="badge badge--inactive"><?= __('inactive') ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="btn-group">
                                    <!-- Changer le rôle -->
                                    <form method="POST"
                                          action="<?= $BASE_URL ?>/admin/stores/<?= $storeId ?>/members/<?= (int) $m['id'] ?>/role"
                                          class="form-inline-flex">
                                        <select name="role" class="form-control form-control-sm">
                                            <?php foreach ($roles as $rVal => $rLabel): ?>
                                                <option value="<?= $rVal ?>" <?= $currentRole === $rVal ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($rLabel) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button type="submit" class="btn btn--ghost btn--sm"><?= __('apply') ?></button>
                                    </form>
                                    <!-- Cotisations individuelles -->
                                    <a href="<?= $BASE_URL ?>/admin/stores/<?= (int) $store['id'] ?>/members/<?= (int) $m['id'] ?>/deductions"
                                       class="btn btn--ghost btn--sm" title="<?= __('deduction_overrides') ?>">💰</a>
                                    <!-- Retirer du store -->
                                    <form method="POST"
                                          action="<?= $BASE_URL ?>/admin/stores/<?= $storeId ?>/members/<?= (int) $m['id'] ?>/delete"
                                          class="form-inline">
                                        <button type="submit" class="btn btn--danger btn--sm"
                                                onclick="return confirm('<?= __('confirm_remove_member') ?>')"><?= __('remove') ?></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <!-- Ajouter un membre -->
    <?php if (!empty($available)): ?>
        <div class="card-body--add">
            <form method="POST" action="<?= $BASE_URL ?>/admin/stores/<?= $storeId ?>/members"
                  class="form-flex--add">
                <div class="form-group form-group--flex1">
                    <label class="form-label"><?= __('user') ?></label>
                    <select name="user_id" class="form-control" required>
                        <option value="">— <?= __('select') ?> —</option>
                        <?php foreach ($available as $u): ?>
                            <?php
                            $uName = trim(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? ''))
                                  ?: ($u['email'] ?? '#' . $u['id']);
                            ?>
                            <option value="<?= (int) $u['id'] ?>"><?= htmlspecialchars($uName) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group form-group--fixed">
                    <label class="form-label"><?= __('role') ?></label>
                    <select name="role" class="form-control">
                        <?php foreach ($roles as $rVal => $rLabel): ?>
                            <option value="<?= $rVal ?>"><?= htmlspecialchars($rLabel) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn btn--primary"><?= __('add') ?></button>
            </form>
        </div>
    <?php else: ?>
        <div class="card-footer--muted">
            <?= __('all_users_already_members') ?>
        </div>
    <?php endif; ?>
</div>

<?php endif; ?>
