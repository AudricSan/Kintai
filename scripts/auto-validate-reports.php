#!/usr/bin/env php
<?php

/**
 * Validation automatique des rapports journaliers non validés.
 *
 * ─── Usage ────────────────────────────────────────────────────────────────────
 *
 *   php scripts/auto-validate-reports.php
 *   php scripts/auto-validate-reports.php --date 2026-04-29
 *   php scripts/auto-validate-reports.php --all-stores
 *
 *   --date YYYY-MM-DD  Date à traiter (défaut : hier)
 *   --all-stores       Ignore le filtre horaire, traite tous les stores
 *
 * ─── Planification sur OVH mutualisé ─────────────────────────────────────────
 *
 *  Option recommandée — endpoint HTTP (Tâches planifiées OVH) :
 *    URL  : https://votresite.com/cron/auto-validate?token=CRON_SECRET
 *    Fréq : Toutes les heures
 *    (La valeur CRON_SECRET doit être définie dans les variables d'env OVH)
 *
 *  Option CLI — Tâches planifiées OVH avec PHP CLI :
 *    /usr/local/php8.3/bin/php /home/votrelogin/www/scripts/auto-validate-reports.php
 *    Fréquence : Toutes les heures
 *    (Chemin PHP OVH : /usr/local/php8.3/bin/php  — vérifier dans l'espace client)
 *
 * ─── Planification sur VPS / serveur dédié Linux ─────────────────────────────
 *
 *   crontab -e
 *   0 * * * * php /var/www/html/scripts/auto-validate-reports.php >> /var/log/kintai-cron.log 2>&1
 *
 * ─── Note ─────────────────────────────────────────────────────────────────────
 *
 *   Le script ne traite que les stores dont l'heure configurée (auto_validate_time)
 *   correspond à l'heure courante (format "H" — "01" pour 01:xx).
 *   Une exécution horaire est donc suffisante.
 *   La date traitée est hier par défaut (rapport du jour précédent).
 */

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));

if (!file_exists(BASE_PATH . '/storage/installed.lock')) {
    fwrite(STDERR, '[Kintai] Application non installée. Exécutez d\'abord php install.php.' . PHP_EOL);
    exit(1);
}

require BASE_PATH . '/vendor/autoload.php';
require BASE_PATH . '/src/Core/helpers.php';

use kintai\Core\Application;
use kintai\Core\Services\DailyReportAutoValidateService;

// ─── Arguments ────────────────────────────────────────────────────────────────

$opts = getopt('', ['date:', 'all-stores']);

$targetDate = isset($opts['date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $opts['date'])
    ? $opts['date']
    : date('Y-m-d', strtotime('yesterday'));

$allStores   = isset($opts['all-stores']);
$currentHour = $allStores ? null : date('H');

// ─── Bootstrap ────────────────────────────────────────────────────────────────

$app = new Application(BASE_PATH);
$app->boot();

/** @var DailyReportAutoValidateService $service */
$service = $app->container()->make(DailyReportAutoValidateService::class);

// ─── Exécution ────────────────────────────────────────────────────────────────

$label = $allStores
    ? 'tous stores (sans filtre horaire)'
    : "heure : {$currentHour}h — stores configurés sur cette heure";

echo sprintf('[%s] Auto-validation — date : %s | %s' . PHP_EOL,
    date('Y-m-d H:i:s'), $targetDate, $label);

$result = $service->run($targetDate, $currentHour);

echo sprintf('[%s] Terminé — %d validé(s), %d ignoré(s)' . PHP_EOL,
    date('Y-m-d H:i:s'), $result['validated'], $result['skipped']);

foreach ($result['errors'] as $err) {
    fwrite(STDERR, '[ERREUR] ' . $err . PHP_EOL);
}

exit(empty($result['errors']) ? 0 : 1);
