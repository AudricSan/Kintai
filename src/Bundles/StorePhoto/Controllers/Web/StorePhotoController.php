<?php

declare(strict_types=1);

namespace kintai\Bundles\StorePhoto\Controllers\Web;

use kintai\UI\Controller\Web\HasAdminAccess;
use kintai\Bundles\StorePhoto\Services\ImageCompressionService;
use kintai\Core\Exceptions\ForbiddenException;
use kintai\Core\Repositories\StorePhotoRepositoryInterface;
use kintai\Core\Repositories\StoreRepositoryInterface;
use kintai\Core\Repositories\AppSettingsRepositoryInterface;
use kintai\Core\Request;
use kintai\Core\Response;
use kintai\Core\Services\AuditLogger;
use kintai\UI\ViewRenderer;

final class StorePhotoController
{
    use HasAdminAccess;

    public function __construct(
        private readonly ViewRenderer $view,
        private readonly StorePhotoRepositoryInterface $photos,
        private readonly StoreRepositoryInterface $stores,
        private readonly AppSettingsRepositoryInterface $appSettings,
        private readonly AuditLogger $auditLogger,
        private readonly ImageCompressionService $imageCompressor,
    ) {}

    public function index(Request $request): Response
    {
        $managedIds = $this->managedIds($request);
        $storeId    = (int) ($request->query('store_id') ?: 0);
        $submissions = $this->photos->findAllSubmissions(null, 100);

        if ($storeId > 0) {
            $submissions = array_values(array_filter($submissions, fn($s) => (int) ($s['store_id'] ?? 0) === $storeId));
        }

        $storeNames = $this->buildStoresMap(null);
        $submissionImages = [];
        foreach ($submissions as $s) {
            $sid = (int) $s['id'];
            $submissionImages[$sid] = $this->photos->findImagesBySubmission($sid);
        }

        return Response::html($this->view->render('store-photos::store-photos', [
            'title'             => __('photos_report'),
            'submissions'       => $submissions,
            'submissionImages'  => $submissionImages,
            'storeNames'        => $storeNames,
            'availableStores'   => $this->availableStores($managedIds),
            'filterStoreId'     => $storeId,
        ], 'layout.app'));
    }

    public function create(Request $request): Response
    {
        $managedIds = $this->managedIds($request);
        $storeId    = (int) ($request->query('store_id') ?: 0);
        $myStores   = $this->availableStores($managedIds);
        $storeNames = $this->buildStoresMap($managedIds);

        if ($managedIds !== null && $storeId > 0) {
            $this->assertStoreAccess($request, $storeId);
        }
        if ($storeId > 0) {
            $this->assertPhotosFeatureEnabled($storeId);
        }

        return Response::html($this->view->render('store-photos::store-photos-form', [
            'title'       => __('photo_new_submission'),
            'mode'        => 'create',
            'myStores'    => $myStores,
            'storeNames'  => $storeNames,
            'prefillStoreId' => $storeId,
        ], 'layout.app'));
    }

    public function store(Request $request): Response
    {
        $managedIds = $this->managedIds($request);
        $storeId    = (int) ($request->post('store_id') ?: 0);
        if ($storeId <= 0) {
            return $request->isAjax()
                ? Response::json(['error' => 'store_id required'], 400)
                : Response::redirect($this->base() . '/admin/photos/create');
        }
        $this->assertStoreAccess($request, $storeId);
        $this->assertPhotosFeatureEnabled($storeId);

        $weekLabel = $request->post('week_label') ?: date('Y-m-d', strtotime('tuesday this week'));
        $notes     = $request->post('notes') ?? '';
        $createdBy = (int) ($request->getAttribute('auth_user')['id'] ?? 0);

        $submission = $this->photos->saveSubmission([
            'store_id'       => $storeId,
            'week_label'     => $weekLabel,
            'notes'          => $notes,
            'image_count'    => 0,
            'retention_days' => max(1, (int) $this->appSettings->get('photo_retention_days', '14')),
            'created_by'     => $createdBy,
            'updated_at'     => date('Y-m-d H:i:s'),
        ]);
        $submissionId = (int) $submission['id'];

        $files = $request->file('photos');
        if ($files !== null && !empty($files['tmp_name'][0])) {
            $this->saveUploadedFiles($request, $storeId, $submissionId, $files);
        }

        $this->auditLogger->log($request, 'photo.submission_created', 'store_photo_submission', $submissionId, $submission, $storeId);

        if ($request->isAjax()) {
            return Response::json(['id' => $submissionId]);
        }
        return Response::redirect($this->base() . '/admin/photos?store_id=' . $storeId);
    }

    public function uploadFile(Request $request): Response
    {
        $submissionId = (int) $request->param('id');
        $submission   = $this->photos->findSubmissionById($submissionId);
        if (!$submission) {
            return Response::json(['error' => 'Submission not found'], 404);
        }
        $this->assertStoreAccess($request, (int) $submission['store_id']);
        $this->assertPhotosFeatureEnabled((int) $submission['store_id']);

        $file = $request->file('photo');
        if ($file === null || !is_uploaded_file($file['tmp_name'])) {
            return Response::json(['error' => 'No file uploaded'], 400);
        }

        $ext = $this->safeImageExtension($file['name'] ?? '');
        if ($ext === null) {
            return Response::json(['error' => 'File type not allowed'], 422);
        }

        $storeId = (int) $submission['store_id'];
        $index   = (int) ($submission['image_count'] ?? 0);

        $uploadDir = dirname(__DIR__, 5) . '/storage/uploads/img/';
        $storeDir  = $uploadDir . $storeId . '/';
        $subDir    = $storeDir . $submissionId . '/';
        if (!is_dir($subDir)) { mkdir($subDir, 0775, true); }

        $compressed = $this->imageCompressor->compress($file['tmp_name'], $subDir . 'photo_' . ($index + 1));
        if ($compressed === null) {
            return Response::json(['error' => 'File type not allowed'], 422);
        }
        $safe = basename($compressed['path']);

        $this->photos->saveImage([
            'submission_id' => $submissionId,
            'filename'      => $file['name'],
            'filepath'      => 'storage/img/' . $storeId . '/' . $submissionId . '/' . $safe,
            'filesize'      => $compressed['size'],
            'mime_type'     => $compressed['mime'],
            'sort_order'    => $index,
        ]);

        $this->photos->saveSubmission([
            'id'          => $submissionId,
            'image_count' => $index + 1,
            'updated_at'  => date('Y-m-d H:i:s'),
        ]);

        return Response::json(['ok' => true, 'file' => $safe]);
    }

    /**
     * Le toggle "photos" par store (/admin/stores/{id}/edit) est décoratif tant qu'il
     * n'est pas vérifié ici — contrairement à EmployeeController/MessageController,
     * ce contrôleur est admin-only donc il n'y a aucune autre surface qui l'applique.
     */
    private function assertPhotosFeatureEnabled(int $storeId): void
    {
        $features = $this->stores->getFeatures($storeId);
        if ($features !== [] && !in_array('photos', $features, true)) {
            throw new ForbiddenException("La fonctionnalité Photos n'est pas activée pour ce magasin.");
        }
    }

    /**
     * Retourne l'extension normalisée si le fichier est une image autorisée, null sinon.
     */
    private function safeImageExtension(string $filename): ?string
    {
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        return in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true) ? $ext : null;
    }

    private function saveUploadedFiles(Request $request, int $storeId, int $submissionId, array $files): void
    {
        $uploadDir = dirname(__DIR__, 5) . '/storage/uploads/img/';
        $storeDir  = $uploadDir . $storeId . '/';
        $subDir    = $storeDir . $submissionId . '/';
        if (!is_dir($storeDir)) { mkdir($storeDir, 0775, true); }
        if (!is_dir($subDir))  { mkdir($subDir, 0775, true); }

        $count    = 0;
        $tmpNames = is_array($files['tmp_name']) ? $files['tmp_name'] : [$files['tmp_name']];
        $names    = is_array($files['name'])      ? $files['name']      : [$files['name']];
        $types    = is_array($files['type'])      ? $files['type']      : [$files['type']];
        $errs     = is_array($files['error'])     ? $files['error']     : [$files['error']];

        foreach ($tmpNames as $i => $tmp) {
            if (!empty($errs[$i]) || !is_uploaded_file($tmp)) continue;
            $ext = $this->safeImageExtension($names[$i] ?? '');
            if ($ext === null) continue;

            $compressed = $this->imageCompressor->compress($tmp, $subDir . 'photo_' . ($count + 1));
            if ($compressed === null) continue;

            $this->photos->saveImage([
                'submission_id' => $submissionId,
                'filename'      => $names[$i],
                'filepath'      => 'storage/img/' . $storeId . '/' . $submissionId . '/' . basename($compressed['path']),
                'filesize'      => $compressed['size'],
                'mime_type'     => $compressed['mime'],
                'sort_order'    => $count,
            ]);
            $count++;
        }

        $this->photos->saveSubmission([
            'id'          => $submissionId,
            'image_count' => $count,
            'updated_at'  => date('Y-m-d H:i:s'),
        ]);
    }

    public function show(Request $request): Response
    {
        $id = (int) $request->param('id');
        $submission = $this->photos->findSubmissionById($id);
        if (!$submission) {
            return Response::html($this->view->render('errors.404', ['title' => '404'], 'layout.app'), 404);
        }

        $images = $this->photos->findImagesBySubmission($id);
        $store  = $this->stores->findById((int) $submission['store_id']);

        return Response::html($this->view->render('store-photos::store-photos-detail', [
            'title'      => __('photo_submission') . ' #' . $id,
            'submission' => $submission,
            'images'     => $images,
            'store'      => $store,
        ], 'layout.app'));
    }

    public function delete(Request $request): Response
    {
        $authUser = $request->getAttribute('auth_user');
        if (empty($authUser['is_admin'])) {
            throw new ForbiddenException('Seul le propriétaire peut supprimer un envoi de photos.');
        }

        $id = (int) $request->param('id');
        $submission = $this->photos->findSubmissionById($id);
        if (!$submission) {
            return Response::redirect($this->base() . '/admin/photos');
        }

        $storeId = (int) $submission['store_id'];

        $uploadDir = dirname(__DIR__, 5) . '/storage/uploads/img/' . $storeId . '/' . $id . '/';
        if (is_dir($uploadDir)) {
            foreach (glob($uploadDir . '*') as $f) { if (is_file($f)) unlink($f); }
            rmdir($uploadDir);
            $storeDir = dirname($uploadDir);
            if (is_dir($storeDir) && count(array_diff(scandir($storeDir), ['.', '..'])) === 0) {
                rmdir($storeDir);
            }
        }

        $this->photos->deleteSubmission($id);
        $this->auditLogger->log($request, 'photo.submission_deleted', 'store_photo_submission', $id, $submission, $storeId);

        return Response::redirect($this->base() . '/admin/photos?store_id=' . $storeId);
    }

    public function settings(Request $request): Response
    {
        $authUser = $request->getAttribute('auth_user');
        if (empty($authUser['is_admin'])) {
            throw new ForbiddenException();
        }

        return Response::html($this->view->render('store-photos::store-photos-settings', [
            'title'               => __('photo_settings'),
            'retentionDays'       => $this->appSettings->get('photo_retention_days', '14'),
            'cleanupDelay'        => $this->appSettings->get('photo_cleanup_delay', '7'),
        ], 'layout.app'));
    }

    public function saveSettings(Request $request): Response
    {
        $authUser = $request->getAttribute('auth_user');
        if (empty($authUser['is_admin'])) {
            throw new ForbiddenException();
        }

        $retentionDays = max(1, (int) ($request->post('photo_retention_days') ?: 14));
        $cleanupDelay  = max(1, (int) ($request->post('photo_cleanup_delay') ?: 7));

        $this->appSettings->setMany([
            'photo_retention_days' => (string) $retentionDays,
            'photo_cleanup_delay'  => (string) $cleanupDelay,
        ]);

        $this->auditLogger->logUpdate($request, 'photo.settings_updated', 'app_settings', 0, [], [], [], 0);

        return Response::redirect($this->base() . '/admin/photos/settings');
    }
}
