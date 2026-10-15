<?php
use kintai\UI\Components\Button;
use kintai\UI\Components\Card;

/**
 * Assistant de configuration (SetupWizardController) : étape « license », « bundles » ou bilan « done ».
 *
 * @var string                    $step
 * @var string|null               $error
 * @var bool|null                 $licenseConfigured
 * @var string|null               $licenseError
 * @var list<array<string, mixed>>|null $bundles
 * @var int|null                  $maxBundles
 * @var array<string, mixed>|null $result
 */
$error ??= null;
$errors = [
    'missing_license_key'    => __('license_missing_key'),
    'license_invalid'        => __('setup_license_invalid'),
    'setup_bundles_unknown'  => __('setup_bundles_unknown'),
    'setup_bundles_too_many' => __('setup_bundles_too_many', ['max' => (int) ($maxBundles ?? 0)]),
];
$stepNumber = ['license' => 1, 'bundles' => 2, 'done' => 3][$step] ?? 1;
?>
<div class="page-header">
    <h2 class="page-header__title"><?= __('setup_title') ?></h2>
</div>

<p class="text-muted mb-sm"><?= __('setup_step_of', ['n' => $stepNumber, 'total' => 3]) ?></p>

<?php if ($error !== null && isset($errors[$error])): ?>
    <div class="alert alert--danger mb-sm"><?= htmlspecialchars($errors[$error]) ?>
        <?php if ($error === 'license_invalid' && !empty($licenseError)): ?> (<?= htmlspecialchars((string) $licenseError) ?>)<?php endif; ?></div>
<?php endif; ?>

<?php if ($step === 'license'): ?>
<?php ob_start(); ?>
    <p class="mb-sm"><?= __('setup_license_intro') ?></p>
    <?php if (empty($licenseConfigured)): ?>
        <div class="alert alert--info mb-sm"><?= __('license_server_not_configured_hint') ?></div>
    <?php endif; ?>
    <form method="POST" action="<?= route_url('admin.setup.license') ?>" class="form-stack">
        <?= csrf_field() ?>
        <div class="form-group">
            <label class="form-label"><?= __('license_key_label') ?></label>
            <input type="text" name="license_key" class="form-control" placeholder="<?= __('license_key_placeholder') ?>">
        </div>
        <div class="form-actions">
            <?= Button::make(__('license_activate_btn'))->primary()->submit()->disabled(empty($licenseConfigured))->render() ?>
        </div>
    </form>
    <form method="POST" action="<?= route_url('admin.setup.license') ?>" class="mt-sm">
        <?= csrf_field() ?>
        <input type="hidden" name="skip" value="1">
        <?= Button::make(__('setup_license_skip'))->ghost()->submit()->render() ?>
    </form>
<?php echo Card::make()->header(__('setup_license_title'))->body(ob_get_clean())->render(); ?>

<?php elseif ($step === 'bundles'): ?>
<?php ob_start(); ?>
    <p class="mb-sm">
        <?= $maxBundles !== null ? __('setup_bundles_intro_max', ['max' => $maxBundles]) : __('setup_bundles_intro_unlimited') ?>
    </p>
    <?php if (empty($bundles)): ?>
        <div class="alert alert--warning mb-sm"><?= __('setup_bundles_catalog_unavailable') ?></div>
    <?php else: ?>
    <form method="POST" action="<?= route_url('admin.setup.bundles') ?>" id="setup-bundles-form"
          data-max="<?= $maxBundles !== null ? (int) $maxBundles : '' ?>">
        <?= csrf_field() ?>
        <div class="dash-widget-list">
            <?php foreach ($bundles as $b): ?>
            <label class="dash-widget-item">
                <input type="checkbox" name="bundles[]" value="<?= htmlspecialchars((string) $b['slug']) ?>">
                <span><strong><?= htmlspecialchars((string) $b['name']) ?></strong>
                    <span class="text-muted text-sm"> — <?= htmlspecialchars((string) $b['description']) ?></span></span>
            </label>
            <?php endforeach; ?>
        </div>
        <?php if ($maxBundles !== null): ?>
            <p class="form-hint" id="setup-bundles-count" data-template="<?= htmlspecialchars(__('setup_bundles_selected', ['count' => '{count}', 'max' => $maxBundles]), ENT_QUOTES) ?>"></p>
        <?php endif; ?>
        <div class="form-actions">
            <?= Button::make(__('setup_bundles_install'))->primary()->submit()->render() ?>
        </div>
        <p class="form-hint"><?= __('setup_bundles_wait') ?></p>
    </form>
    <?php endif; ?>
    <form method="POST" action="<?= route_url('admin.setup.bundles') ?>" class="mt-sm">
        <?= csrf_field() ?>
        <input type="hidden" name="skip" value="1">
        <?= Button::make(__('setup_bundles_skip'))->ghost()->submit()->render() ?>
    </form>
<?php echo Card::make()->header(__('setup_bundles_title'))->body(ob_get_clean())->render(); ?>
<script src="<?= $BASE_URL ?>/assets/js/modules/setup-wizard.js?v=<?= asset_version() ?>"></script>

<?php else: ?>
<?php ob_start(); ?>
    <?php if (!empty($result['installed'])): ?>
        <div class="alert alert--success mb-sm"><?= htmlspecialchars(__('setup_done_installed', ['list' => implode(', ', $result['installed'])])) ?></div>
    <?php endif; ?>
    <?php if (!empty($result['inactive'])): ?>
        <div class="alert alert--warning mb-sm"><?= htmlspecialchars(__('setup_done_inactive', ['list' => implode(', ', $result['inactive'])])) ?></div>
    <?php endif; ?>
    <?php if (!empty($result['failed'])): ?>
        <div class="alert alert--warning mb-sm">
            <?= htmlspecialchars(__('setup_done_failed')) ?>
            <ul class="text-sm">
                <?php foreach ($result['failed'] as $slug => $reason): ?>
                    <li><strong><?= htmlspecialchars((string) $slug) ?></strong> — <?= htmlspecialchars((string) $reason) ?></li>
                <?php endforeach; ?>
            </ul>
            <a href="<?= route_url('admin.bundles.market') ?>"><?= htmlspecialchars(__('setup_done_open_market')) ?></a>
        </div>
    <?php endif; ?>
    <p class="mb-sm"><?= __('setup_done_intro') ?></p>
    <div class="form-actions">
        <?= Button::make(__('setup_done_dashboard'))->primary()->link(base_url() . '/')->render() ?>
    </div>
<?php echo Card::make()->header(__('setup_done_title'))->body(ob_get_clean())->render(); ?>
<?php endif; ?>
