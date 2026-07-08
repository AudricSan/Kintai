<?php

declare(strict_types=1);

namespace kintai\Core\Cron;

use kintai\Core\Exceptions\ForbiddenException;
use kintai\Core\Exceptions\NotFoundException;
use kintai\Core\Repositories\CronTokenRepositoryInterface;
use kintai\Core\Request;
use kintai\Core\Response;

final class CronRunner
{
    /** @var array<string, CronJobInterface> */
    private array $jobs = [];

    public function __construct(
        private readonly CronTokenRepositoryInterface $cronTokens,
    ) {}

    public function register(CronJobInterface $job): void
    {
        $this->jobs[$job->getName()] = $job;
    }

    public function run(string $jobName, Request $request): Response
    {
        $job = $this->jobs[$jobName] ?? null;
        if ($job === null) {
            throw new NotFoundException("Job cron '$jobName' inconnu.");
        }

        $rawToken = $this->extractToken($request);
        if ($rawToken === null) {
            throw new ForbiddenException('Token cron requis.');
        }

        $hashed = hash_token($rawToken);
        $record = $this->cronTokens->findByToken($hashed);
        if ($record === null) {
            throw new ForbiddenException('Token cron invalide.');
        }

        if (!empty($record['expires_at']) && $record['expires_at'] < date('Y-m-d H:i:s')) {
            throw new ForbiddenException('Token cron expiré.');
        }

        if (isset($record['job_name']) && $record['job_name'] !== '' && $record['job_name'] !== $jobName) {
            throw new ForbiddenException('Ce token n\'est pas autorisé pour le job demandé.');
        }

        $this->cronTokens->touchLastUsed((int) $record['id']);

        return $job->run($request);
    }

    /** @return CronJobInterface[] */
    public function getJobs(): array
    {
        return $this->jobs;
    }

    public function getJob(string $name): ?CronJobInterface
    {
        return $this->jobs[$name] ?? null;
    }

    /** @return array{name: string, label: string}[] */
    public function getJobMeta(): array
    {
        $meta = [];
        foreach ($this->jobs as $name => $job) {
            $label = match ($name) {
                'auto-validate'  => 'Validation auto des rapports',
                'backup'         => 'Backup de la base de données',

                default          => $name,
            };
            $meta[] = ['name' => $name, 'label' => $label];
        }
        return $meta;
    }

    private function extractToken(Request $request): ?string
    {
        $fromQuery = $request->query('token', '');
        if ($fromQuery !== '') {
            return $fromQuery;
        }

        $header = $request->header('Authorization');
        if ($header !== null && preg_match('/^Bearer\s+(.+)$/i', $header, $m)) {
            return $m[1];
        }

        return null;
    }
}
