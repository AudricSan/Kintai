<?php

/**
 * Fusionne rétroactivement les envois de photos déjà en base qui datent du même
 * jour pour un même magasin, en un seul rapport. Voir
 * StorePhotoConsolidationService pour le détail de la fusion.
 *
 * Usage:
 *   php scripts/consolidate-daily-photo-reports.php --dry-run   # rapport, aucune écriture
 *   php scripts/consolidate-daily-photo-reports.php             # applique la fusion (idempotent, ré-exécutable)
 */

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));

require BASE_PATH . '/vendor/autoload.php';
require BASE_PATH . '/src/Core/helpers.php';

use kintai\Bundles\StorePhoto\Services\StorePhotoConsolidationService;
use kintai\Core\Application;
use kintai\Core\Repositories\StorePhotoRepositoryInterface;

$dryRun = in_array('--dry-run', $argv, true);

$app = new Application(BASE_PATH);
$app->boot();
$photos = $app->container()->make(StorePhotoRepositoryInterface::class);

$service = new StorePhotoConsolidationService($photos, BASE_PATH . '/storage/uploads/img/');
$report  = $service->consolidate($dryRun);

foreach ($report['groups'] as $group) {
    printf(
        "[Store #%d] %s : fusion de l'envoi %s dans #%d%s\n",
        $group['store_id'],
        $group['day'],
        implode(', #', $group['merged_ids']) !== '' ? '#' . implode(', #', $group['merged_ids']) : '',
        $group['target_id'],
        $dryRun ? ' [dry-run]' : ''
    );
}

printf(
    "%s%d jour(s)/magasin(s) fusionné(s), %d envoi(s) supprimé(s) après fusion.\n",
    $dryRun ? '[dry-run] ' : '',
    $report['merged_groups'],
    $report['merged_submissions']
);
