<?php

declare(strict_types=1);

namespace kintai\UI\Controller\Web\System;

use kintai\Core\Database\MigrationRunner;
use kintai\Core\Exceptions\ForbiddenException;
use kintai\Core\Request;
use kintai\Core\Response;
use kintai\Core\Services\AppSettingsService;
use kintai\Core\Services\BackupService;
use kintai\Core\Services\GithubUpdateService;
use kintai\Core\Services\UpdateService;
use kintai\UI\ViewRenderer;

final class BackupController
{
    public function __construct(
        private readonly ViewRenderer $view,
        private readonly BackupService $backup,
        private readonly UpdateService $update,
        private readonly GithubUpdateService $githubUpdate,
        private readonly MigrationRunner $migrator,
        private readonly AppSettingsService $settings,
    ) {}

    /** GET /admin/backup/download?filename= — télécharge une archive de sauvegarde existante. */
    public function download(Request $request): Response
    {
        $this->requireOwner($request);

        $filename = (string) $request->query('filename', '');
        $path = $this->backup->getPath($filename);
        if ($filename === '' || !file_exists($path)) {
            return Response::redirect('/admin/backup?success=error_not_found');
        }

        return Response::fileStream($path, 'application/zip', basename($path));
    }

    public function index(Request $request): Response
    {
        $this->requireOwner($request);

        $backups = $this->backup->list();
        $flash = $this->describeFlash((string) $request->query('success', ''));

        return Response::html($this->view->render('system.backup', [
            'title'   => 'Sauvegardes',
            'backups' => $backups,
            'flash'   => $flash,
        ], 'layout.app'));
    }

    public function updatePage(Request $request): Response
    {
        $this->requireOwner($request);

        $flash = $this->describeFlash((string) $request->query('success', ''));

        $updateInfo = $this->githubUpdate->checkLatestRelease();
        $releaseNotesCondensed = $updateInfo !== null
            ? $this->githubUpdate->condenseReleaseNotes((string) $updateInfo['release_notes'])
            : '';

        $currentChannel = $this->settings->updateChannel();
        $otherChannelsHistory = [];
        foreach (['release', 'beta', 'alpha'] as $channel) {
            if ($channel === $currentChannel) {
                continue;
            }
            $otherChannelsHistory[$channel] = $this->githubUpdate->getReleaseHistory($channel);
        }

        return Response::html($this->view->render('system.update', [
            'title'                     => 'Mises à jour',
            'currentVersion'            => $this->update->getCurrentVersion(),
            'updateInfo'                => $updateInfo,
            'updateCheckError'          => $this->githubUpdate->getLastCheckError(),
            'updateChannel'             => $currentChannel,
            'otherChannelsHistory'      => $otherChannelsHistory,
            'pendingMigs'               => $this->update->getPendingMigrations(),
            'lastUpdateDurationSeconds' => $this->update->getLastUpdateDuration(),
            'releaseNotesCondensed'     => $releaseNotesCondensed,
            'repoReleasesUrl'           => $this->githubUpdate->getRepoReleasesUrl(),
            'flash'                     => $flash,
        ], 'layout.app'));
    }

    /** POST /admin/update/channel — change le canal de mise à jour suivi (release/beta/alpha). */
    public function saveChannel(Request $request): Response
    {
        $this->requireOwner($request);

        $channel = (string) $request->post('channel', 'release');
        if (!in_array($channel, ['alpha', 'beta', 'release'], true)) {
            $channel = 'release';
        }

        $this->settings->setMany(['update_channel' => $channel]);

        return Response::redirect('/admin/update?success=channel_' . $channel);
    }

    /** POST /admin/update/apply — applique la dernière release GitHub disponible. */
    public function update(Request $request): Response
    {
        $this->requireOwner($request);

        $wasMaintenanceEnabled = $this->settings->maintenanceModeEnabled();
        if (!$wasMaintenanceEnabled) {
            $this->settings->setMany(['maintenance_mode_enabled' => '1']);
        }

        try {
            try {
                $result = $this->githubUpdate->applyUpdate();
            } catch (\Throwable $e) {
                return Response::redirect('/admin/update?success=error_' . urlencode($e->getMessage()));
            }

            if (!$result['ok']) {
                return Response::redirect('/admin/update?success=error_' . urlencode((string) $result['error']));
            }

            $summary = sprintf(
                'updated_%s_files-%d_deleted-%d_migrations-%d_composer-%s',
                $result['version'],
                $result['files_copied'],
                $result['files_deleted'],
                $result['migrations_applied'],
                $result['composer'],
            );

            return Response::redirect('/admin/update?success=' . urlencode($summary));
        } finally {
            if (!$wasMaintenanceEnabled) {
                $this->settings->setMany(['maintenance_mode_enabled' => '0']);
            }
        }
    }

    /**
     * POST /admin/update/stream — applique la dernière release GitHub
     * disponible en streamant la progression (SSE). Même contrat que
     * MessageStreamController::stream() : ne retourne jamais réellement,
     * termine par exit(0).
     */
    public function updateStream(Request $request): Response
    {
        $this->requireOwner($request);

        session_write_close();
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('X-Accel-Buffering: no');
        header('Connection: keep-alive');
        set_time_limit(0);

        $emit = function (string $event, array $data): void {
            echo "event: {$event}\n";
            echo 'data: ' . json_encode($data) . "\n\n";
            flush();
        };

        // exit() n'exécute pas les blocs finally en PHP : on restaure la maintenance
        // explicitement avant chaque exit() plutôt que de compter sur un try/finally.
        $wasMaintenanceEnabled = $this->settings->maintenanceModeEnabled();
        if (!$wasMaintenanceEnabled) {
            $this->settings->setMany(['maintenance_mode_enabled' => '1']);
        }
        $restoreMaintenance = function () use ($wasMaintenanceEnabled): void {
            if (!$wasMaintenanceEnabled) {
                $this->settings->setMany(['maintenance_mode_enabled' => '0']);
            }
        };

        try {
            $result = $this->githubUpdate->applyUpdate(function (int $percent, string $label) use ($emit): void {
                $emit('progress', ['percent' => $percent, 'label' => $label]);
            });
        } catch (\Throwable $e) {
            $restoreMaintenance();
            $emit('error', ['message' => $e->getMessage()]);
            exit(0);
        }

        if (!$result['ok']) {
            $restoreMaintenance();
            $emit('error', ['message' => (string) $result['error']]);
            exit(0);
        }

        $restoreMaintenance();
        $emit('done', $result);
        exit(0);
    }

    public function create(Request $request): Response
    {
        $this->requireOwner($request);

        $note = trim((string) $request->post('note', ''));

        try {
            $result = $this->backup->create($note ?: null);
            $this->backup->enforceRetention($this->settings->backupMaxKeep());
            return Response::redirect('/admin/backup?success=created_' . urlencode($result['filename']));
        } catch (\Throwable $e) {
            return Response::redirect('/admin/backup?success=error_' . urlencode($e->getMessage()));
        }
    }

    public function restore(Request $request): Response
    {
        $this->requireOwner($request);

        $filename = trim((string) $request->post('filename', ''));

        if ($filename === '') {
            return Response::redirect('/admin/backup?success=error_no_file');
        }

        try {
            $this->backup->restore($filename);
            $this->backup->enforceRetention($this->settings->backupMaxKeep());
            return Response::redirect('/admin/backup?success=restored');
        } catch (\Throwable $e) {
            return Response::redirect('/admin/backup?success=error_' . urlencode($e->getMessage()));
        }
    }

    public function delete(Request $request): Response
    {
        $this->requireOwner($request);

        $filename = trim((string) $request->post('filename', ''));

        if ($filename === '') {
            return Response::redirect('/admin/backup?success=error_no_file');
        }

        $this->backup->delete($filename);
        return Response::redirect('/admin/backup?success=deleted');
    }

    public function migrate(Request $request): Response
    {
        $this->requireOwner($request);

        $wasMaintenanceEnabled = $this->settings->maintenanceModeEnabled();
        if (!$wasMaintenanceEnabled) {
            $this->settings->setMany(['maintenance_mode_enabled' => '1']);
        }

        try {
            try {
                $count = $this->migrator->run();
                $request->setAttribute('_log_extra', ['migrations_applied' => $count]);
                return Response::redirect('/admin/update?success=migrated');
            } catch (\Throwable $e) {
                $request->setAttribute('_log_extra', ['migration_error' => $e->getMessage()]);
                return Response::redirect('/admin/update?success=error_' . urlencode($e->getMessage()));
            }
        } finally {
            if (!$wasMaintenanceEnabled) {
                $this->settings->setMany(['maintenance_mode_enabled' => '0']);
            }
        }
    }

    public function deleteAll(Request $request): Response
    {
        $this->requireOwner($request);

        $count = $this->backup->deleteAll();
        return Response::redirect('/admin/backup?success=deleted_all_' . $count);
    }

    /**
     * Traduit le code brut de ?success=... (voir les redirections de ce contrôleur)
     * en message affichable, stylé succès/erreur. Sans ça, la vue affichait le code
     * tel quel (ex. "error_SQLSTATE[...]...") dans une boîte neutre non stylée en
     * erreur — voir CHANGELOG. Les tests existants continuent d'asserter sur le code
     * brut dans l'URL de redirection : seul ce parsing, côté affichage, change.
     *
     * @return array{type: 'success'|'danger', text: string}|null
     */
    private function describeFlash(string $raw): ?array
    {
        if ($raw === '') {
            return null;
        }
        $decoded = urldecode($raw);

        return match (true) {
            $decoded === 'restored' => ['type' => 'success', 'text' => __('backup_flash_restored')],
            $decoded === 'deleted'  => ['type' => 'success', 'text' => __('backup_flash_deleted')],
            $decoded === 'migrated' => ['type' => 'success', 'text' => __('backup_flash_migrated')],
            str_starts_with($decoded, 'created_') => ['type' => 'success', 'text' => __('backup_flash_created', [
                'filename' => substr($decoded, strlen('created_')),
            ])],
            str_starts_with($decoded, 'deleted_all_') => ['type' => 'success', 'text' => __('backup_flash_deleted_all', [
                'count' => substr($decoded, strlen('deleted_all_')),
            ])],
            str_starts_with($decoded, 'channel_') => ['type' => 'success', 'text' => __('backup_flash_channel', [
                'channel' => substr($decoded, strlen('channel_')),
            ])],
            str_starts_with($decoded, 'updated_') && preg_match(
                '/^updated_(?<version>.+)_files-(?<copied>\d+)_deleted-(?<deleted>\d+)_migrations-(?<migrations>\d+)_composer-/',
                $decoded,
                $m
            ) === 1 => ['type' => 'success', 'text' => strtr(__('backup_update_done_summary'), [
                '{version}'    => $m['version'],
                '{copied}'     => $m['copied'],
                '{deleted}'    => $m['deleted'],
                '{migrations}' => $m['migrations'],
            ])],
            $decoded === 'error_no_file'            => ['type' => 'danger', 'text' => __('backup_flash_error_no_file')],
            $decoded === 'error_not_found'           => ['type' => 'danger', 'text' => __('backup_flash_error_not_found')],
            $decoded === 'error_no_update_available' => ['type' => 'danger', 'text' => __('backup_flash_error_no_update_available')],
            $decoded === 'error_download_failed'     => ['type' => 'danger', 'text' => __('backup_flash_error_download_failed')],
            $decoded === 'error_extract_failed'      => ['type' => 'danger', 'text' => __('backup_flash_error_extract_failed')],
            str_starts_with($decoded, 'error_') => ['type' => 'danger', 'text' => __('error_prefix') . substr($decoded, strlen('error_'))],
            default => ['type' => 'success', 'text' => $decoded],
        };
    }

    private function requireOwner(Request $request): void
    {
        $user = $request->getAttribute('auth_user');
        if (empty($user['is_admin'])) {
            throw new ForbiddenException('Réservé au propriétaire.');
        }
    }
}
