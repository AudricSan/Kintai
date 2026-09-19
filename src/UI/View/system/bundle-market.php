<?php
use kintai\UI\Components\Badge;

/**
 * @var array $entries Liste de bundles catalogués, voir BundleMarketController::market().
 * @var string|null $BASE_URL
 * @var string|null $error
 * @var string|null $success
 * @var string|null $uninstalled
 */
?>
<div class="page-header">
    <h2 class="page-header__title"><?= __('bundle_market') ?> <span class="page-count">(<?= count($entries) ?>)</span></h2>
</div>

<?php include __DIR__ . '/../_partials/_settings-tabs.php'; ?>
<?php include __DIR__ . '/_bundle-tabs.php'; ?>

<?php if ($success): ?>
    <div class="alert alert--success mb-sm"><?= htmlspecialchars(__('bundle_market_install_success', ['slug' => $success])) ?></div>
<?php endif; ?>
<?php if ($uninstalled): ?>
    <div class="alert alert--success mb-sm"><?= htmlspecialchars(__('bundle_market_uninstall_success', ['slug' => $uninstalled])) ?></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert alert--danger mb-sm"><?= htmlspecialchars(urldecode($error)) ?></div>
<?php endif; ?>

<div class="card card--mb">
    <div class="card-body">
        <p class="form-hint"><?= __('bundle_market_hint') ?></p>
    </div>
</div>

<?php if (empty($entries)): ?>
    <div class="card"><div class="card-body"><p class="form-hint"><?= __('bundle_market_none_found') ?></p></div></div>
<?php endif; ?>

<div class="bundle-market-grid">
    <?php foreach ($entries as $entry): ?>
        <div class="card bundle-market-card">
            <div class="card-body">
                <h3 class="card-title">
                    <?= htmlspecialchars($entry['name']) ?>
                    <?php if ($entry['official']): ?>
                        <?= Badge::make(__('official'))->primary()->sm()->render() ?>
                    <?php else: ?>
                        <?= Badge::make(__('bundle_third_party'))->warning()->sm()->render() ?>
                    <?php endif; ?>
                </h3>
                <p class="text-sm"><?= htmlspecialchars($entry['description']) ?></p>
                <?php if ($entry['orphaned']): ?>
                    <p class="form-hint text-danger"><?= __('bundle_market_orphaned_hint') ?></p>
                <?php else: ?>
                    <p class="form-hint"><?= __('bundle_market_from_registry', ['registry' => $entry['registry_name']]) ?></p>
                <?php endif; ?>

                <?php if ($entry['installed_version'] !== null): ?>
                    <p class="text-sm">
                        <?= __('bundle_market_installed_version', ['version' => $entry['installed_version']]) ?>
                        <?php if ($entry['update_available']): ?>
                            <?= Badge::make(__('bundle_market_update_available', ['version' => $entry['latest_version']]))->success()->sm()->render() ?>
                        <?php endif; ?>
                    </p>
                <?php else: ?>
                    <p class="text-sm form-hint"><?= __('bundle_market_not_installed') ?></p>
                <?php endif; ?>

                <?php if (!$entry['official']): ?>
                    <p class="form-hint text-danger"><?= __('bundle_market_third_party_warning') ?></p>
                <?php endif; ?>

                <?php if (!$entry['orphaned'] && ($entry['installed_version'] === null || $entry['update_available'])): ?>
                <form method="POST" action="<?= $BASE_URL ?>/admin/bundles/market/install"
                      class="bundle-market-install-form form-stack" data-stream-url="<?= $BASE_URL ?>/admin/bundles/market/install/stream"
                      data-dry-run-url="<?= $BASE_URL ?>/admin/bundles/market/dry-run"
                      data-dry-run-ok-label="<?= htmlspecialchars(__('bundle_market_dry_run_ok'), ENT_QUOTES) ?>"
                      data-generic-error-label="<?= htmlspecialchars(__('bundle_market_generic_error'), ENT_QUOTES) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="slug" value="<?= htmlspecialchars($entry['slug']) ?>">
                    <input type="hidden" name="repository_url" value="<?= htmlspecialchars($entry['repository_url']) ?>">
                    <input type="hidden" name="registry_url" value="<?= htmlspecialchars($entry['registry_url']) ?>">

                    <?php if (count($entry['versions']) > 1): ?>
                        <div class="form-group">
                            <label class="form-label"><?= __('bundle_market_version') ?></label>
                            <select name="version" class="form-control form-control-sm">
                                <?php foreach ($entry['versions'] as $v): ?>
                                    <option value="<?= htmlspecialchars($v) ?>"><?= htmlspecialchars($v) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php else: ?>
                        <input type="hidden" name="version" value="<?= htmlspecialchars($entry['latest_version'] ?? '') ?>">
                    <?php endif; ?>

                    <?php if (!$entry['official']): ?>
                        <label class="form-check">
                            <input type="checkbox" name="confirm_third_party" value="1" required>
                            <?= __('bundle_market_confirm_third_party') ?>
                        </label>
                    <?php endif; ?>

                    <div class="bundle-market-progress hidden" data-progress>
                        <div class="progress-bar"><div class="progress-bar__fill" data-progress-fill></div></div>
                        <p class="text-sm mt-xs" data-progress-label></p>
                    </div>

                    <div class="form-actions">
                        <button type="button" class="btn btn--ghost btn--sm" data-dry-run-btn><?= __('bundle_market_test') ?></button>
                        <button type="submit" class="btn btn--primary btn--sm">
                            <?= $entry['installed_version'] !== null ? __('bundle_market_update') : __('bundle_market_install') ?>
                        </button>
                    </div>
                </form>
                <?php endif; ?>

                <?php if ($entry['installed_version'] !== null): ?>
                    <form method="POST" action="<?= $BASE_URL ?>/admin/bundles/market/uninstall" class="form-inline mt-sm"
                          data-confirm="<?= htmlspecialchars(__('bundle_market_uninstall_confirm', ['name' => $entry['name']]), ENT_QUOTES) ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="slug" value="<?= htmlspecialchars($entry['slug']) ?>">
                        <button type="submit" class="btn btn--danger btn--sm"><?= __('bundle_market_uninstall') ?></button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<script src="<?= $BASE_URL ?>/assets/js/modules/bundle-market.js?v=<?= asset_version() ?>"></script>
