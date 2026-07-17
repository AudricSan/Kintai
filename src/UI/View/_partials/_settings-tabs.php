<?php
/**
 * Bandeau d'onglets partagé par les pages "Paramètres" (identité, feedbacks, sauvegarde, mises à jour, langues).
 * @var string|null $BASE_URL
 */

$_uri  = '/' . trim(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), '/');
$_base = rtrim($BASE_URL ?? '', '/');
$_settingsPath = $_base !== '' && str_starts_with($_uri, $_base) ? substr($_uri, strlen($_base)) : $_uri;
$_settingsPath = '/' . trim($_settingsPath, '/') ?: '/';

$_settingsTabs = [
    ['label' => __('settings'),  'url' => route_url('admin.owner_settings'), 'match' => '/admin/owner-settings'],
];
if (bundle_enabled('feedback')) {
    $_settingsTabs[] = ['label' => __('feedbacks'), 'url' => route_url('admin.feedbacks'), 'match' => '/admin/feedbacks'];
}
$_settingsTabs[] = ['label' => __('backup'),    'url' => route_url('admin.backup'),        'match' => '/admin/backup'];
$_settingsTabs[] = ['label' => __('update'),    'url' => route_url('admin.update'),        'match' => '/admin/update'];
$_settingsTabs[] = ['label' => __('languages'), 'url' => route_url('admin.languages'),     'match' => '/admin/languages'];
$_settingsTabs[] = ['label' => __('bundle_settings'), 'url' => route_url('admin.bundles'), 'match' => '/admin/bundles'];
$_settingsTabs[] = ['label' => __('roles'), 'url' => route_url('admin.roles'), 'match' => '/admin/roles'];
?>
<div class="tabs card--mb">
    <?php foreach ($_settingsTabs as $_t): ?>
        <a href="<?= $_t['url'] ?>" class="tab<?= str_starts_with($_settingsPath, $_t['match']) ? ' tab--active' : '' ?>">
            <?= $_t['label'] ?>
        </a>
    <?php endforeach; ?>
</div>
