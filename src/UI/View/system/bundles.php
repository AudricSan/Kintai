<?php
use kintai\UI\Components\Button;
use kintai\UI\Components\Flash;

/** @var array $bundles Liste de ['key' => string, 'label' => string, 'desc' => string, 'enabled' => bool] */
/** @var bool  $success */

echo Flash::fromQuery('success', ['default' => __('save_success')])->render();
?>
<div class="page-header">
    <h2 class="page-header__title"><?= __('bundle_settings') ?></h2>
</div>

<?php include __DIR__ . '/../_partials/_settings-tabs.php'; ?>

<div class="card card--mb">
    <div class="card-body">
        <p class="form-hint"><?= __('bundle_settings_hint') ?></p>
    </div>
</div>

<form method="POST" action="<?= route_url('admin.bundles') ?>">
    <?= csrf_field() ?>

    <div class="card">
        <div class="card-body form-stack">
            <div class="feature-cards">
                <?php foreach ($bundles as $b): ?>
                    <label class="feature-card">
                        <input type="checkbox" name="bundle_<?= htmlspecialchars($b['key']) ?>" value="1"
                               <?= $b['enabled'] ? 'checked' : '' ?> class="feature-card__input">
                        <div class="feature-card__body">
                            <span class="feature-card__title"><?= htmlspecialchars($b['label']) ?></span>
                            <span class="feature-card__desc"><?= htmlspecialchars($b['desc']) ?></span>
                        </div>
                        <div class="feature-card__toggle">
                            <span class="toggle-switch">
                                <span class="toggle-switch__track"></span>
                                <span class="toggle-switch__thumb"></span>
                            </span>
                            <span class="toggle-switch__label">
                                <?= $b['enabled'] ? __('active') : __('inactive') ?>
                            </span>
                        </div>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div class="form-actions">
        <?= Button::make(__('save'))->primary()->submit()->render() ?>
    </div>
</form>
