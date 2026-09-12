<?php

declare(strict_types=1);

namespace kintai\Bundles\StorePhoto\Services;

use kintai\Core\Repositories\StorePhotoRepositoryInterface;

/**
 * Fusionne rétroactivement les envois de photos déjà en base qui datent du même
 * jour pour un même magasin, en un seul rapport — le comportement que
 * StorePhotoController::store() applique désormais aux nouveaux envois (voir
 * StorePhotoRepositoryInterface::findTodaySubmission()) n'a d'effet qu'à partir
 * de son déploiement ; ce service applique la même règle une fois aux envois
 * déjà existants (utilisé par scripts/consolidate-daily-photo-reports.php).
 *
 * Pour chaque groupe (store_id, jour de created_at) comptant plusieurs envois,
 * le plus ancien devient le rapport unique du jour ; les photos et notes des
 * autres lui sont réassignées (fichiers déplacés sur disque, lignes
 * store_photo_images réassignées en continuant la numérotation), puis les
 * envois désormais vides sont supprimés.
 */
final class StorePhotoConsolidationService
{
    public function __construct(
        private readonly StorePhotoRepositoryInterface $photos,
        private readonly string $photoDir, // storage/uploads/img/, terminé par un "/"
    ) {}

    /**
     * @return array{
     *     groups: list<array{store_id:int, day:string, target_id:int, merged_ids:int[]}>,
     *     merged_groups:int,
     *     merged_submissions:int,
     * }
     */
    public function consolidate(bool $dryRun = false): array
    {
        $groups = $this->groupByStoreAndDay($this->photos->findAllSubmissions(null, 1000000));

        $report = ['groups' => [], 'merged_groups' => 0, 'merged_submissions' => 0];

        foreach ($groups as $subs) {
            if (count($subs) < 2) {
                continue;
            }

            // Le plus ancien envoi du jour devient le rapport unique ; les autres s'y fondent.
            usort($subs, fn (array $a, array $b) => strcmp((string) $a['created_at'], (string) $b['created_at']));
            $target   = array_shift($subs);
            $targetId = (int) $target['id'];
            $storeId  = (int) $target['store_id'];

            $imageCount = (int) ($target['image_count'] ?? 0);
            $notes      = trim((string) ($target['notes'] ?? ''));
            $mergedIds  = [];

            foreach ($subs as $sub) {
                $subId       = (int) $sub['id'];
                $mergedIds[] = $subId;

                if (!$dryRun) {
                    $imageCount = $this->moveImages($storeId, $subId, $targetId, $imageCount);

                    if (!empty($sub['notes'])) {
                        $notes = $notes !== '' ? $notes . "\n" . trim((string) $sub['notes']) : trim((string) $sub['notes']);
                    }

                    $this->cleanupSourceDir($storeId, $subId);
                    $this->photos->deleteSubmission($subId);
                }

                $report['merged_submissions']++;
            }

            if (!$dryRun) {
                $this->photos->saveSubmission([
                    'id'          => $targetId,
                    'image_count' => $imageCount,
                    'notes'       => $notes !== '' ? $notes : null,
                    'updated_at'  => date('Y-m-d H:i:s'),
                ]);
            }

            $report['groups'][] = [
                'store_id'   => $storeId,
                'day'        => substr((string) $target['created_at'], 0, 10),
                'target_id'  => $targetId,
                'merged_ids' => $mergedIds,
            ];
            $report['merged_groups']++;
        }

        return $report;
    }

    /** @param array[] $submissions @return array<string, array[]> groupées par "store_id|jour" */
    private function groupByStoreAndDay(array $submissions): array
    {
        $groups = [];
        foreach ($submissions as $sub) {
            $day = substr((string) ($sub['created_at'] ?? ''), 0, 10);
            if ($day === '') {
                continue;
            }
            $groups[$sub['store_id'] . '|' . $day][] = $sub;
        }
        return $groups;
    }

    /** Déplace les fichiers d'un envoi fusionné vers l'envoi cible et réassigne leurs lignes. Retourne le nouveau image_count. */
    private function moveImages(int $storeId, int $sourceSubId, int $targetSubId, int $imageCount): int
    {
        $images = $this->photos->findImagesBySubmission($sourceSubId);

        $sourceDir = $this->photoDir . $storeId . '/' . $sourceSubId . '/';
        $targetDir = $this->photoDir . $storeId . '/' . $targetSubId . '/';
        if ($images !== [] && !is_dir($targetDir)) {
            mkdir($targetDir, 0775, true);
        }

        foreach ($images as $img) {
            $ext         = pathinfo((string) $img['filepath'], PATHINFO_EXTENSION);
            $newBasename = 'photo_' . ($imageCount + 1) . ($ext !== '' ? '.' . $ext : '');
            $sourcePath  = $sourceDir . basename((string) $img['filepath']);
            $newPath     = $targetDir . $newBasename;

            if (is_file($sourcePath)) {
                rename($sourcePath, $newPath);
            }

            $this->photos->saveImage([
                'id'            => $img['id'],
                'submission_id' => $targetSubId,
                'filepath'      => 'storage/img/' . $storeId . '/' . $targetSubId . '/' . $newBasename,
                'sort_order'    => $imageCount,
            ]);
            $imageCount++;
        }

        return $imageCount;
    }

    /** Le dossier de l'envoi fusionné est déjà vidé de ses fichiers (déplacés par moveImages()). */
    private function cleanupSourceDir(int $storeId, int $subId): void
    {
        $sourceDir = $this->photoDir . $storeId . '/' . $subId . '/';
        if (!is_dir($sourceDir)) {
            return;
        }
        foreach (glob($sourceDir . '*') as $f) {
            if (is_file($f)) {
                unlink($f);
            }
        }
        @rmdir($sourceDir);
    }
}
