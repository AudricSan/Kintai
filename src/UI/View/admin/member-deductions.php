<?php
/**
 * Formulaire de déductions sociales individuelles d'un membre
 *
 * @var array  $store
 * @var array  $membership
 * @var array  $user
 * @var array  $deductionSettings   taux du magasin
 * @var array  $deductionOverrides  valeurs de l'employé
 * @var string $BASE_URL
 */

$empName = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''))
    ?: ($user['display_name'] ?? ($user['email'] ?? ''));

$ov = $deductionOverrides;
$ds = $deductionSettings;

$fields = [
    'health_insurance'     => ['label_key' => 'ded_health_insurance',     'rate_key' => 'health_insurance_rate',     'is_flat' => false],
    'pension'              => ['label_key' => 'ded_pension',              'rate_key' => 'pension_rate',              'is_flat' => false],
    'employment_insurance' => ['label_key' => 'ded_employment_insurance', 'rate_key' => 'employment_insurance_rate', 'is_flat' => false],
    'income_tax'           => ['label_key' => 'ded_income_tax',           'rate_key' => 'income_tax_rate',           'is_flat' => false],
    'resident_tax_monthly' => ['label_key' => 'ded_resident_tax',         'rate_key' => 'resident_tax_monthly',      'is_flat' => true],
];
?>

<div class="page-header">
    <h2 class="page-header__title">
        <?= __('deduction_overrides') ?> — <?= htmlspecialchars($empName) ?>
        <span class="page-count"><?= htmlspecialchars($store['name'] ?? '') ?></span>
    </h2>
    <div class="page-header__actions">
        <a href="<?= $BASE_URL ?>/admin/stores/<?= (int) $store['id'] ?>/edit" class="btn btn--ghost btn--sm">← <?= __('back') ?></a>
    </div>
</div>

<?php if ($flash = ($_GET['success'] ?? '')): ?>
<div class="alert alert--success"><?= __('operation_success') ?></div>
<?php endif; ?>

<div class="card card--narrow">
    <div class="card-body">
        <p class="form-hint">
            <?= __('deduction_overrides_hint') ?>
        </p>

        <form method="POST" action="<?= $BASE_URL ?>/admin/stores/<?= (int) $store['id'] ?>/members/<?= (int) $membership['id'] ?>/deductions">

            <?php foreach ($fields as $key => $cfg): ?>
            <?php
                $val         = isset($ov[$key]) && $ov[$key] !== null ? (string) $ov[$key] : '';
                $storeVal    = (float) ($ds[$cfg['rate_key']] ?? 0);
                $placeholder = $cfg['is_flat']
                    ? __('ded_store_default', ['val' => $storeVal])
                    : __('ded_auto_from_rate', ['rate' => $storeVal]);
                $step        = $cfg['is_flat'] ? '1' : '0.01';
                $unit        = $cfg['is_flat']
                    ? ($store['currency'] ?? '')
                    : '%';
                $hint        = $cfg['is_flat']
                    ? __('leave_blank_for_store_default')
                    : __('leave_blank_for_store_rate');
            ?>
            <div class="form-group">
                <label class="form-label"><?= __($cfg['label_key']) ?></label>
                <div class="ded-input-group">
                    <input type="number" name="ded_<?= $key ?>"
                           class="form-control ded-input-standalone"
                           step="<?= $step ?>" min="0" <?= $cfg['is_flat'] ? '' : 'max="100"' ?>
                           value="<?= htmlspecialchars($val) ?>"
                           placeholder="<?= htmlspecialchars($placeholder) ?>">
                    <span class="ded-field-hint">
                        <?= htmlspecialchars($unit) ?> — <?= $hint ?>
                    </span>
                </div>
            </div>
            <?php endforeach; ?>

            <div class="form-actions">
                <button type="submit" class="btn btn--primary"><?= __('save') ?></button>
                <a href="<?= $BASE_URL ?>/admin/stores/<?= (int) $store['id'] ?>/edit" class="btn btn--ghost"><?= __('cancel') ?></a>
            </div>
        </form>
    </div>
</div>
