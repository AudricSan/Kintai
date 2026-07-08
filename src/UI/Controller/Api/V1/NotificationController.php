<?php

declare(strict_types=1);

namespace kintai\UI\Controller\Api\V1;

use kintai\Core\Api\Paginator;
use kintai\Core\Exceptions\NotFoundException;
use kintai\Core\Repositories\NotificationRepositoryInterface;
use kintai\Core\Request;
use kintai\Core\Response;

final class NotificationController
{
    public function __construct(private readonly NotificationRepositoryInterface $notifications) {}

    /**
     * GET /api/v1/notifications?user_id=X&limit=20
     * user_id obligatoire.
     */
    public function index(Request $request): Response
    {
        $userId = $request->query('user_id');
        if ($userId === null) {
            $authUser = $request->getAttribute('auth_user');
            $userId   = $authUser['id'] ?? null;
        }

        [$page, $limit] = Paginator::params($request);
        $items = $userId !== null
            ? $this->notifications->findByUser((int) $userId, 1000)
            : [];

        return Response::json(Paginator::paginate($items, $page, $limit));
    }

    /** GET /api/v1/notifications/{id} */
    public function show(Request $request): Response
    {
        $item = $this->notifications->findById((int) $request->param('id'));
        if ($item === null) {
            throw new NotFoundException('Notification introuvable.');
        }
        return Response::json($item);
    }

    /** POST /api/v1/notifications/{id}/read */
    public function markRead(Request $request): Response
    {
        $id   = (int) $request->param('id');
        $item = $this->notifications->findById($id);
        if ($item === null) {
            throw new NotFoundException('Notification introuvable.');
        }

        $authUser = $request->getAttribute('auth_user');
        $this->notifications->markRead($id, (int) ($authUser['id'] ?? $item['user_id']));
        return Response::empty();
    }

    /** POST /api/v1/notifications/read-all?user_id=X */
    public function markAllRead(Request $request): Response
    {
        $userId = $request->query('user_id');
        if ($userId === null) {
            $authUser = $request->getAttribute('auth_user');
            $userId   = $authUser['id'] ?? 0;
        }
        $this->notifications->markAllRead((int) $userId);
        return Response::empty();
    }

    /** DELETE /api/v1/notifications/{id} */
    public function destroy(Request $request): Response
    {
        $id   = (int) $request->param('id');
        $item = $this->notifications->findById($id);
        if ($item === null) {
            throw new NotFoundException('Notification introuvable.');
        }

        $authUser = $request->getAttribute('auth_user');
        $this->notifications->markRead($id, (int) ($authUser['id'] ?? $item['user_id']));
        return Response::empty();
    }
}
