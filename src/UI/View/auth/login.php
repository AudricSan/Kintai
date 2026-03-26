<?php
/** @var bool   $error */
/** @var string $login_mode  'code' | 'email' */
$mode = ($login_mode ?? 'code') === 'email' ? 'email' : 'code';
?>

<?php if ($error ?? false): ?>
    <div class="alert alert--error mb-sm">
        <?= __('invalid_credentials') ?>
    </div>
<?php endif; ?>

<!-- Onglets -->
<div class="login-tabs">
    <a href="?mode=code"
       class="login-tab <?= $mode === 'code' ? 'active' : '' ?>">
        <?= __('login_code') ?>
    </a>
    <a href="?mode=email"
       class="login-tab <?= $mode === 'email' ? 'active' : '' ?>">
        <?= __('login_email') ?>
    </a>
</div>

<form method="POST" action="<?= $BASE_URL ?>/login">
    <input type="hidden" name="login_mode" value="<?= $mode ?>">
    <div class="form-stack">

        <?php if ($mode === 'code'): ?>
            <!-- ── Mode employé : code employé + code magasin + mdp ─── -->
            <div class="form-group">
                <label class="form-label form-label--required"><?= __('employee_code') ?></label>
                <input type="text" name="employee_code" class="form-control input-code"
                       placeholder="ex : EMP001"
                       autocomplete="username" autofocus required>
            </div>

            <div class="form-group">
                <label class="form-label form-label--required"><?= __('store_code') ?></label>
                <input type="text" name="store_code" class="form-control input-code"
                       placeholder="ex : HQ"
                       autocomplete="organization" required>
            </div>

            <div class="form-group">
                <label class="form-label form-label--required"><?= __('password') ?></label>
                <input type="password" name="password" class="form-control"
                       autocomplete="current-password" placeholder="0000" required>
                <p class="login-hint"><?= __('password_default_hint', ['code' => '0000']) ?></p>
            </div>

        <?php else: ?>
            <!-- ── Mode admin / manager : email + mdp ─────────────── -->
            <div class="form-group">
                <label class="form-label form-label--required"><?= __('email_address') ?></label>
                <input type="email" name="email" class="form-control"
                       placeholder="vous@exemple.com"
                       autocomplete="email" autofocus required>
            </div>

            <div class="form-group">
                <label class="form-label form-label--required"><?= __('password') ?></label>
                <input type="password" name="password" class="form-control"
                       autocomplete="current-password" required>
            </div>

        <?php endif; ?>

        <button type="submit" class="btn btn--primary btn--full">
            <?= __('connect') ?>
        </button>

    </div>
</form>
