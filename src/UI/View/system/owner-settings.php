<?php
use kintai\UI\Components\Button;
use kintai\UI\Components\Card;
use kintai\UI\Components\Flash;

/** @var array  $settings */
/** @var bool   $success */

echo Flash::fromQuery('success', ['default' => __('save_success')])->render();
?>
<div class="page-header">
    <h2 class="page-header__title"><?= __('owner_settings') ?></h2>
</div>

<?php include __DIR__ . '/../_partials/_settings-tabs.php'; ?>

<form method="POST" action="<?= route_url('admin.owner_settings') ?>">
    <?= csrf_field() ?>

    <?php
    ob_start();
    ?>
    <div class="form-group">
        <label class="form-label"><?= __('company_name') ?></label>
        <input type="text" name="app_subtitle" class="form-control"
               maxlength="100"
               placeholder="<?= __('company_name_placeholder') ?>"
               value="<?= htmlspecialchars($settings['app_subtitle'] ?? '', ENT_QUOTES) ?>">
        <p class="form-hint"><?= __('company_name_hint') ?></p>
    </div>
    <?php
    echo Card::make()->header(__('identity'))->body(ob_get_clean())->render();
    ?>

    <?php
    ob_start();
    ?>
    <div class="form-group">
        <label class="form-label"><?= __('login_notice') ?></label>
        <textarea name="app_login_notice" class="form-control" rows="3"
                  maxlength="300"
                  placeholder="<?= __('login_notice_placeholder') ?>"><?= htmlspecialchars($settings['app_login_notice'] ?? '', ENT_QUOTES) ?></textarea>
        <p class="form-hint"><?= __('login_notice_hint') ?></p>
    </div>
    <?php
    echo Card::make()->header(__('login_page'))->body(ob_get_clean())->render();
    ?>

    <?php
    ob_start();
    ?>
    <div class="form-group">
        <label class="form-label"><?= __('support_email') ?></label>
        <input type="email" name="app_support_email" class="form-control"
               placeholder="<?= __('support_email_placeholder') ?>"
               value="<?= htmlspecialchars($settings['app_support_email'] ?? '', ENT_QUOTES) ?>">
        <p class="form-hint"><?= __('support_email_hint') ?></p>
    </div>
    <?php
    echo Card::make()->header(__('contact_support'))->body(ob_get_clean())->render();
    ?>

    <?php
    ob_start();
    ?>
    <div class="form-group">
        <label class="form-label"><?= __('backup_auto_enabled') ?></label>
        <label class="form-toggle">
            <input type="checkbox" name="backup_auto_enabled" value="1" class="form-toggle__input"
                   <?= ($settings['backup_auto_enabled'] ?? '1') === '1' ? 'checked' : '' ?>>
            <span class="form-toggle__track"></span>
        </label>
        <p class="form-hint"><?= __('backup_auto_hint') ?></p>
    </div>
    <div class="form-group">
        <label class="form-label" for="backup_max_keep"><?= __('backup_max_keep') ?></label>
        <input type="number" id="backup_max_keep" name="backup_max_keep" class="form-control"
               min="0" max="365" style="width:100px"
               value="<?= (int) ($settings['backup_max_keep'] ?? 0) ?>">
        <p class="form-hint"><?= __('backup_max_keep_hint') ?></p>
    </div>
    <?php
    echo Card::make()->header(__('backup_card_title'))->body(ob_get_clean())->render();
    ?>

    <?php
    ob_start();
    ?>
    <div class="form-group">
        <label class="form-label"><?= __('maintenance_mode_enabled') ?></label>
        <label class="form-toggle">
            <input type="checkbox" name="maintenance_mode_enabled" value="1" class="form-toggle__input"
                   <?= ($settings['maintenance_mode_enabled'] ?? '0') === '1' ? 'checked' : '' ?>>
            <span class="form-toggle__track"></span>
        </label>
        <p class="form-hint"><?= __('maintenance_mode_hint') ?></p>
    </div>
    <div class="form-group">
        <label class="form-label"><?= __('maintenance_message') ?></label>
        <textarea name="maintenance_message" class="form-control" rows="3"
                  maxlength="500"
                  placeholder="<?= __('maintenance_message_placeholder') ?>"><?= htmlspecialchars($settings['maintenance_message'] ?? '', ENT_QUOTES) ?></textarea>
        <p class="form-hint"><?= __('maintenance_message_hint') ?></p>
    </div>
    <?php
    echo Card::make()->header(__('maintenance_mode'))->body(ob_get_clean())->render();
    ?>

    <div class="form-actions">
        <?= Button::make(__('save'))->primary()->submit()->render() ?>
        <a href="<?= route_url('home') ?>" class="btn btn--ghost"><?= __('cancel') ?></a>
    </div>
</form>
