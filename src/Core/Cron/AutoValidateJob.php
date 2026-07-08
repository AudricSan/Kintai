<?php

declare(strict_types=1);

namespace kintai\Core\Cron;

use kintai\Core\Request;
use kintai\Core\Response;
use kintai\Core\Services\AuditLogger;
use kintai\Core\Services\DailyReportAutoValidateService;

final class AutoValidateJob implements CronJobInterface
{
    public function __construct(
        private readonly DailyReportAutoValidateService $autoValidate,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function getName(): string
    {
        return 'auto-validate';
    }

    public function run(Request $request): Response
    {
        set_time_limit(0);

        $rawDate    = $request->query('date', '');
        $targetDate = preg_match('/^\d{4}-\d{2}-\d{2}$/', $rawDate)
            ? $rawDate
            : date('Y-m-d', strtotime('yesterday'));

        $all         = $request->query('all', '0') === '1';
        $currentHour = $all ? null : date('H');

        $result = $this->autoValidate->run($targetDate, $currentHour);

        $this->auditLogger->log($request, 'cron.auto_validated', 'system', null, [
            'date'      => $targetDate,
            'validated' => $result['validated'],
            'skipped'   => $result['skipped'],
            'errors'    => $result['errors'] ?? 0,
        ]);

        return Response::json([
            'ok'        => true,
            'date'      => $targetDate,
            'validated' => $result['validated'],
            'skipped'   => $result['skipped'],
            'errors'    => $result['errors'],
        ]);
    }
}
