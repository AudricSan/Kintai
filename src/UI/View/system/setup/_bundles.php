<?php
use kintai\UI\Components\Button;
use kintai\UI\Components\Card;

/**
 * @var list<array<string, mixed>>|null $bundles
 * @var int|null                        $maxBundles
 */
ob_start();
?>
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
