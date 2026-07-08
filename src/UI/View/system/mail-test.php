<?php
use kintai\UI\Components\Badge;
use kintai\UI\Components\Button;
use kintai\UI\Components\Card;

/**
 * @var array       $mailConfig
 * @var array       $phpIni
 * @var array|null  $result
 * @var string      $last_to
 * @var string      $BASE_URL
 */

$driver      = $mailConfig['driver'] ?? 'native';
$from        = ($mailConfig['from']['address'] ?? '') . ' (' . ($mailConfig['from']['name'] ?? '') . ')';
$smtp        = $mailConfig['smtp'] ?? [];
$smtpHost    = ($smtp['host'] ?? '') . ':' . ($smtp['port'] ?? '');
$smtpUser    = $smtp['username'] ?? '';
$smtpEnc     = $smtp['encryption'] ?? '';
$hasPh       = fn(string $v): bool => str_contains($v, 'A_CHANGER') || str_contains($v, 'example.com');
?>
<div class="page-header">
    <h2 class="page-header__title">Test d'envoi de mail</h2>
    <div class="page-header__actions">
        <a href="<?= route_url('home') ?>" class="btn btn--ghost btn--sm">← Retour</a>
    </div>
</div>

<?php if ($result !== null): ?>
    <div class="alert alert--<?= $result['success'] ? 'success' : 'danger' ?> mb-sm">
        <?php if ($result['success']): ?>
            Mail envoyé avec succès à <strong><?= htmlspecialchars($last_to) ?></strong>. Vérifiez votre boîte de réception.
        <?php else: ?>
            <strong>Échec de l'envoi.</strong><br>
            <code><?= htmlspecialchars($result['error'] ?? 'Erreur inconnue') ?></code>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php
ob_start();
?>
<table class="table table--compact mw-600">
    <tbody>
        <tr><th class="w-180">Driver</th><td><code><?= htmlspecialchars($driver) ?></code> <?= $driver === 'native' ? Badge::make('mail() PHP')->warning()->render() : Badge::make('SMTP')->info()->render() ?></td></tr>
        <tr><th>From</th><td><code><?= htmlspecialchars($from) ?></code> <?= $hasPh($mailConfig['from']['address'] ?? '') ? Badge::make('Placeholder !')->danger()->render() : '' ?></td></tr>
        <?php if ($driver === 'smtp'): ?>
        <tr><th>Serveur SMTP</th><td><code><?= htmlspecialchars($smtpHost) ?></code> <?= $hasPh($smtp['host'] ?? '') ? Badge::make('Placeholder !')->danger()->render() : '' ?></td></tr>
        <tr><th>Username</th><td><code><?= htmlspecialchars($smtpUser) ?></code> <?= $hasPh($smtpUser) ? Badge::make('Placeholder !')->danger()->render() : '' ?></td></tr>
        <tr><th>Mot de passe</th><td><?php
            if (($smtp['password'] ?? '') === '') echo '<span class="text-muted">(vide)</span>';
            elseif ($hasPh($smtp['password'] ?? '')) echo '<code>' . htmlspecialchars($smtp['password'] ?? '') . '</code> ' . Badge::make('Placeholder !')->danger()->render();
            else echo '<code>••••••••</code> <span class="text-muted">(défini)</span>';
        ?></td></tr>
        <tr><th>Chiffrement</th><td><code><?= htmlspecialchars($smtpEnc) ?></code></td></tr>
        <?php else: ?>
        <tr><th>php.ini SMTP</th><td><code><?= htmlspecialchars($phpIni['SMTP'] ?? '') ?></code></td></tr>
        <tr><th>php.ini smtp_port</th><td><code><?= htmlspecialchars($phpIni['smtp_port'] ?? '') ?></code></td></tr>
        <tr><th>sendmail_path</th><td><code><?= htmlspecialchars($phpIni['sendmail_path'] ?? '') ?></code></td></tr>
        <?php endif; ?>
    </tbody>
</table>
<?php if ($driver === 'native'): ?>
    <div class="alert alert--warning mt-sm">
        <strong>Driver natif (mail())</strong> — sur Windows/XAMPP, <code>mail()</code> nécessite un vrai serveur SMTP
        configuré dans <code>php.ini</code> (<code>SMTP</code> et <code>smtp_port</code>).
        Passez à <code>MAIL_DRIVER=smtp</code> dans <code>public/.htaccess</code> pour utiliser un serveur SMTP externe.
    </div>
<?php endif; ?>
<?php echo Card::make()->header('Configuration actuelle')->body(ob_get_clean())->render(); ?>

<?php echo Card::make()
    ->header('Configuration dans <code>public/.htaccess</code>')
    ->body('<p class="mb-sm text-muted">Modifiez les lignes <code>SetEnv</code> avec vos vraies valeurs :</p>
        <pre class="code-block">SetEnv MAIL_DRIVER      smtp
SetEnv MAIL_FROM_ADDRESS noreply@votredomaine.com
SetEnv MAIL_FROM_NAME   Kintai
SetEnv MAIL_HOST        pro1.mail.ovh.net
SetEnv MAIL_PORT        465
SetEnv MAIL_USERNAME    noreply@votredomaine.com
SetEnv MAIL_PASSWORD    votre_mot_de_passe_smtp
SetEnv MAIL_ENCRYPTION  ssl</pre>
        <p class="form-hint mt-sm">OVH mutualisé : hôte <code>pro1.mail.ovh.net</code>, port <code>465</code>, chiffrement <code>ssl</code>.<br>
        Gmail : hôte <code>smtp.gmail.com</code>, port <code>587</code>, chiffrement <code>tls</code> + mot de passe d\'application.</p>')
    ->render() ?>

<?php
ob_start();
?>
<form method="POST" action="<?= htmlspecialchars(route_url('admin.mail_test')) ?>">
    <?= csrf_field() ?>
    <div class="form-group mw-420">
        <label class="form-label" for="mail_test_to">Adresse destinataire</label>
        <input type="email" id="mail_test_to" name="to" class="form-control"
               placeholder="votre@email.com" value="<?= htmlspecialchars($last_to) ?>" required>
        <span class="form-hint">Un mail de test sera envoyé à cette adresse via la configuration ci-dessus.</span>
    </div>
    <div class="form-actions">
        <?= Button::make('Envoyer le mail de test')->primary()->submit()->render() ?>
    </div>
</form>
<?php echo Card::make()->header('Envoyer un mail de test')->body(ob_get_clean())->render(); ?>
