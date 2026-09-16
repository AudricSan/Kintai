<?php
declare(strict_types=1);

use kintai\Core\Application;

define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/vendor/autoload.php';
require BASE_PATH . '/src/Core/helpers.php';

$app = new Application(BASE_PATH);
$app->boot();

// ---------------------------------------------------------------
$settings   = $app->container()->make(\kintai\Core\Services\AppSettingsService::class);
$mailer     = $app->container()->make(\kintai\Core\Mail\MailerService::class);
$photoRepo  = $app->container()->make(\kintai\Core\Repositories\StorePhotoRepositoryInterface::class);
$userRepo   = $app->container()->make(\kintai\Core\Repositories\UserRepositoryInterface::class);
$storeRepo  = $app->container()->make(\kintai\Core\Repositories\StoreRepositoryInterface::class);

$retentionDays = max(1, (int) $settings->get('photo_retention_days', '14'));
$cleanupDelay  = max(1, (int) $settings->get('photo_cleanup_delay', '7'));
$photoDir      = BASE_PATH . '/storage/uploads/img/';

echo "[Photo Retention] Retention: {$retentionDays}d, Cleanup delay: {$cleanupDelay}d\n";

// ---- 1. Compression des soumissions expirées ----
$pending = $photoRepo->getPendingCompression($retentionDays);
echo "[Photo Retention] " . count($pending) . " soumission(s) à compresser.\n";

$ownerEmail = null;
$ownerName  = 'Propriétaire';

foreach ($pending as $sub) {
    $sid      = (int) $sub['id'];
    $storeId  = (int) ($sub['store_id'] ?? 0);
    $subDir   = $photoDir . $storeId . '/' . $sid . '/';
    $zipPath  = $photoDir . $storeId . '/' . $sid . '.zip';

    if (!is_dir($subDir)) {
        echo "  [SKIP] {$sid} : dossier introuvable\n";
        $photoRepo->saveSubmission(['id' => $sid, 'compressed' => 1, 'compressed_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')]);
        continue;
    }

    $storeZipDir = dirname($zipPath);
    if (!is_dir($storeZipDir)) { mkdir($storeZipDir, 0775, true); }
    $zip = new ZipArchive();
    if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        echo "  [ERREUR] {$sid} : impossible de créer l'archive zip\n";
        continue;
    }

    $files = glob($subDir . '*');
    foreach ($files as $file) {
        if (is_file($file)) {
            $zip->addFile($file, basename($file));
        }
    }
    $zip->close();

    $photoRepo->saveSubmission([
        'id'             => $sid,
        'compressed'     => 1,
        'compressed_at'  => date('Y-m-d H:i:s'),
        'updated_at'     => date('Y-m-d H:i:s'),
    ]);

    echo "  [OK] {$sid} compressé (" . count($files) . " fichiers)\n";

    // ---- 2. Notification email (une seule fois, pas de spam) ----
    if (empty($sub['mail_sent_at'])) {
        if ($ownerEmail === null) {
            $owner = $userRepo->findAll();
            foreach ($owner as $u) {
                if (!empty($u['is_admin']) && !empty($u['email'])) {
                    $ownerEmail = $u['email'];
                    $ownerName  = $u['name'] ?? 'Propriétaire';
                    break;
                }
            }
        }

        $store = $storeRepo->findById($storeId);
        $storeName = $store['name'] ?? '#' . $storeId;

        if ($ownerEmail) {
            $subject = '[Kintai] Photos archivées — ' . $storeName . ' (' . ($sub['week_label'] ?? '?') . ')';
            $body = "
                <p>Bonjour {$ownerName},</p>
                <p>Les photos de la soumission du <strong>" . htmlspecialchars($sub['week_label'] ?? '?') . "</strong>
                pour le magasin <strong>" . htmlspecialchars($storeName) . "</strong> ont été archivées.</p>
                <p>Période de rétention dépassée : {$retentionDays} jours.</p>
                <p>Ces photos seront définitivement supprimées dans {$cleanupDelay} jours.</p>
                <hr>
                <p style='color:#888;font-size:12px'>Kintai — Gestion automatisée des photos</p>
            ";

            $ok = $mailer->send([$ownerEmail], $subject, $body);
            if ($ok) {
                $photoRepo->saveSubmission(['id' => $sid, 'mail_sent_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')]);
                echo "  [MAIL] Notification envoyée à {$ownerEmail}\n";
            } else {
                echo "  [MAIL] Échec d'envoi à {$ownerEmail}\n";
            }
        }
    }
}

// ---- 3. Suppression définitive après le délai de nettoyage ----
$expired = $photoRepo->getExpiredSubmissions($retentionDays + $cleanupDelay);
echo "[Photo Retention] " . count($expired) . " soumission(s) à purger.\n";

foreach ($expired as $sub) {
    $sid     = (int) $sub['id'];
    $storeId = (int) ($sub['store_id'] ?? 0);
    $subDir  = $photoDir . $storeId . '/' . $sid . '/';
    $zipPath = $photoDir . $storeId . '/' . $sid . '.zip';

    if (is_dir($subDir)) {
        foreach (glob($subDir . '*') as $f) { if (is_file($f)) unlink($f); }
        rmdir($subDir);
        $storeDir = dirname($subDir);
        if (is_dir($storeDir) && count(array_diff(scandir($storeDir), ['.', '..'])) === 0) {
            rmdir($storeDir);
        }
    }
    if (is_file($zipPath)) {
        unlink($zipPath);
    }

    $photoRepo->deleteSubmission($sid);
    echo "  [PURGE] {$sid} supprimé définitivement\n";
}

echo "[Photo Retention] Terminé.\n";
