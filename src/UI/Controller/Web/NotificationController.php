<?php

declare(strict_types=1);

namespace kintai\UI\Controller\Web;

use kintai\Core\Auth\AuthService;
use kintai\Core\Repositories\NotificationRepositoryInterface;
use kintai\Core\Request;
use kintai\Core\Response;
use kintai\UI\ViewRenderer;

final class NotificationController
{
    public function __construct(
        private readonly AuthService $auth,
        private readonly NotificationRepositoryInterface $repo,
        private readonly ViewRenderer $view,
    ) {}

    public function index(Request $request): Response
    {
        $userId        = (int) $this->auth->user()['id'];
        $notifications = $this->repo->findByUser($userId, 50);

        return Response::html($this->view->render('notifications.index', [
            'title'         => __('notifications'),
            'notifications' => $notifications,
        ], 'layout.app'));
    }

    public function markRead(Request $request): Response
    {
        $userId = (int) $this->auth->user()['id'];
        $id     = (int) $request->param('id');
        $this->repo->markRead($id, $userId);

        if ($request->isAjax()) {
            return Response::json(['ok' => true]);
        }

        $ref = $request->post('redirect', '');
        return Response::redirect($ref ?: '/notifications');
    }

    public function markAllRead(Request $request): Response
    {
        $userId = (int) $this->auth->user()['id'];
        $this->repo->markAllRead($userId);

        if ($request->isAjax()) {
            return Response::json(['ok' => true]);
        }

        return Response::redirect('/notifications');
    }

    /**
     * Endpoint de polling : renvoie les notifications non lues créées après $since.
     * Appelé toutes les 15 s par le JS pour afficher les toasts en temps réel.
     */
    public function poll(Request $request): Response
    {
        $userId = (int) $this->auth->user()['id'];
        $since  = $request->query('since', '');

        // Convertir la date ISO 8601 (ex: 2026-05-13T12:00:00.000Z) en Y-m-d H:i:s
        if ($since !== '') {
            $ts = strtotime($since);
            $since = $ts !== false ? date('Y-m-d H:i:s', $ts) : date('Y-m-d H:i:s');
        } else {
            $since = date('Y-m-d H:i:s');
        }

        $recent = $this->repo->findUnreadSince($userId, $since);
        $count  = $this->repo->countUnread($userId);

        return Response::json([
            'notifications' => array_map(fn($n) => [
                'id'   => (int) $n['id'],
                'type' => $n['type'] ?? '',
                'body' => $n['body'] ?? '',
            ], $recent),
            'unread_count' => $count,
        ]);
    }
}
