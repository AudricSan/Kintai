<?php

declare(strict_types=1);

namespace kintai\Core\Cron;

use kintai\Core\Repositories\DatabaseAppSettingsRepository;
use kintai\Core\Request;
use kintai\Core\Response;
use kintai\Core\Services\AppSettingsService;
use kintai\Core\Services\AuditLogger;
use kintai\Core\Services\BackupService;

final class BackupJob implements CronJobInterface
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly ?AppSettingsService $settings = null,
        private readonly ?BackupService $backupService = null,
    ) {
        $this->settings ??= new AppSettingsService(
            new DatabaseAppSettingsRepository()
        );
        $this->backupService ??= new BackupService();
    }

    public function getName(): string
    {
        return 'backup';
    }

    public function run(Request $request): Response
    {
        if (!$this->settings->backupAutoEnabled()) {
            return Response::json(['ok' => false, 'error' => 'Backup automatique désactivé.']);
        }

        set_time_limit(0);

        $note = trim((string) $request->query('note', ''));
        $note = $note ?: '[auto] Backup quotidien';

        try {
            $result = $this->backupService->create($note);
            $this->backupService->enforceRetention($this->settings->backupMaxKeep());

            $this->auditLogger->log($request, 'backup.created', 'system', null, [
                'filename' => $result['filename'],
                'size'     => $result['size'],
                'source'   => 'cron',
            ]);

            return Response::json([
                'ok'       => true,
                'filename' => $result['filename'],
                'size'     => $result['size'],
            ]);
        } catch (\RuntimeException $e) {
            return Response::json([
                'ok'    => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
