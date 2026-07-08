<?php

declare(strict_types=1);

namespace kintai\UI\Controller\Web\System;

use kintai\Core\Repositories\LogRepositoryInterface;
use kintai\Core\Repositories\UserRepositoryInterface;
use kintai\Core\Request;
use kintai\Core\Response;
use kintai\UI\ViewRenderer;

final class ActivityController
{
    public function __construct(
        private readonly ViewRenderer $view,
        private readonly LogRepositoryInterface $logs,
        private readonly UserRepositoryInterface $users,
    ) {}

    public function index(Request $request): Response
    {
        $tab = $request->query('tab', 'activity');

        if ($tab === 'error-file') {
            return $this->errorLogTab($request);
        }

        $filters = [
            'level'         => $request->query('level'),
            'channel'       => $request->query('channel'),
            'action'        => $request->query('action'),
            'resource_type' => $request->query('resource_type'),
            'user_id'       => (int) ($request->query('user_id') ?? 0) ?: null,
            'from'          => $request->query('from'),
            'to'            => $request->query('to'),
            'query'         => $request->query('query'),
        ];
        $filters = array_filter($filters, fn($v) => $v !== null && $v !== '');

        $page    = max(1, (int) ($request->query('page') ?? 1));
        $perPage = 100;

        $rows         = $this->logs->findAll($page, $perPage, $filters);
        $total        = $this->logs->countAll($filters);
        $channels     = $this->logs->findChannels();
        $actions      = $this->logs->findActions();
        $resourceTypes = $this->logs->findResourceTypes();

        $levels = [
            LogRepositoryInterface::LEVEL_DEBUG,
            LogRepositoryInterface::LEVEL_INFO,
            LogRepositoryInterface::LEVEL_WARNING,
            LogRepositoryInterface::LEVEL_ERROR,
            LogRepositoryInterface::LEVEL_CRITICAL,
        ];

        $usersMap = [];
        foreach ($this->users->findAll() as $u) {
            $uid = (int) $u['id'];
            $name = $u['display_name'] ?? trim(($u['last_name'] ?? '') . ' ' . ($u['first_name'] ?? ''));
            $usersMap[$uid] = $name ? sprintf('%s (%s)', $name, $u['email'] ?? '') : ($u['email'] ?? sprintf('user #%d', $uid));
        }

        $totalPages = (int) ceil($total / $perPage);

        return Response::html($this->view->render('system.activity-log', [
            'title'          => 'Journal d\'activité',
            'tab'            => 'activity',
            'rows'           => $rows,
            'total'          => $total,
            'page'           => $page,
            'totalPages'     => $totalPages,
            'levels'         => $levels,
            'channels'       => $channels,
            'actions'        => $actions,
            'resource_types' => $resourceTypes,
            'users_map'      => $usersMap,
            'filters'        => $filters,
        ], 'layout.app'));
    }

    private function errorLogTab(Request $request): Response
    {
        $lines = max(10, min(5000, (int) ($request->query('lines') ?? 200)));
        $path  = dirname(__DIR__, 4) . '/storage/logs/error.log';

        $content = [];
        if (file_exists($path)) {
            $all = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if ($all !== false) {
                $content = array_slice($all, -$lines);
            }
        }

        return Response::html($this->view->render('system.activity-log', [
            'title'      => 'Fichier error.log',
            'tab'        => 'error-file',
            'err_content' => $content,
            'err_lines'  => $lines,
            'err_path'   => $path,
            'err_size'   => file_exists($path) ? filesize($path) : 0,
        ], 'layout.app'));
    }
}
