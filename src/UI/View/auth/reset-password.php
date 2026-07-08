<?php
use kintai\UI\Components\Alert;
use kintai\UI\Components\Button;

/** @var bool        $valid   Le token est valide et non expiré */
/** @var bool        $success Le mot de passe a été réinitialisé avec succès */
/** @var string|null $error   Message d'erreur à afficher */
/** @var string      $token   Le token dans l'URL */
/** @var string      $BASE_URL */
/** @var string|null $login_url */
?>

<?php if ($success ?? false): ?>

    <?= Alert::make('Votre mot de passe a été réinitialisé avec succès.')->success()->render() ?>
    <p class="login-hint login-hint--center">
        <?= Button::make('Se connecter')->primary()->link($login_url ?? route_url('auth.login'))->render() ?>
    </p>

<?php elseif (!($valid ?? false)): ?>

    <?= Alert::make('Ce lien de réinitialisation est invalide ou a expiré. Veuillez refaire une demande.')->danger()->render() ?>
    <p class="login-hint login-hint--center">
        <a href="<?= route_url('password.forgot') ?>">Nouvelle demande</a>
    </p>

<?php else: ?>

    <?php if (!empty($error)):
        echo Alert::make(htmlspecialchars($error, ENT_QUOTES))->danger()->render();
    endif; ?>

    <h2 class="guest-subtitle">Nouveau mot de passe</h2>
    <p class="login-hint">Choisissez un mot de passe d'au moins 8 caractères.</p>

    <form method="POST"
          action="<?= $BASE_URL ?>/reset-password/<?= htmlspecialchars($token, ENT_QUOTES) ?>">
        <?= csrf_field() ?>
        <div class="form-stack">
            <div class="form-group">
                <label class="form-label form-label--required">Nouveau mot de passe</label>
                <input type="password" name="password" class="form-control"
                       autocomplete="new-password" minlength="8" required autofocus>
            </div>

            <div class="form-group">
                <label class="form-label form-label--required">Confirmer le mot de passe</label>
                <input type="password" name="password_confirmation" class="form-control"
                       autocomplete="new-password" minlength="8" required>
            </div>

            <?= Button::make('Enregistrer le nouveau mot de passe')->primary()->full()->submit()->render() ?>
        </div>
    </form>

<?php endif; ?>
