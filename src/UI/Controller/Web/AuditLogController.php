<?php

declare(strict_types=1);

namespace kintai\UI\Controller\Web;

use kintai\Core\Repositories\AuditLogRepositoryInterface;
use kintai\Core\Repositories\UserRepositoryInterface;
use kintai\Core\Request;
use kintai\Core\Response;
use kintai\UI\ViewRenderer;

final class AuditLogController
{
    public function __construct(
        private readonly ViewRenderer $view,
        private readonly AuditLogRepositoryInterface $auditLog,
        private readonly UserRepositoryInterface $users,
    ) {}

    public function index(Request $request): Response
    {
        $logs = $this->auditLog->findRecent(1000);

        // Construire la map user_id → nom pour l'affichage
        $usersMap = [];
        foreach ($this->users->findAll() as $u) {
            $name = trim(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? ''));
            $usersMap[(int) $u['id']] = $name ?: ($u['email'] ?? '#' . $u['id']);
        }

        return Response::html($this->view->render('admin.audit-log', [
            'title'     => 'Journal d\'activité',
            'logs'      => $logs,
            'users_map' => $usersMap,
        ], 'layout.app'));
    }
}
