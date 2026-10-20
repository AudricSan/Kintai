<?php

declare(strict_types=1);

namespace kintai\UI\Controller\Web;

use kintai\Core\Auth\AuthService;
use kintai\Core\Exceptions\NotFoundException;
use kintai\Core\Exceptions\ValidationException;
use kintai\Core\Repositories\DevicePushTokenRepositoryInterface;
use kintai\Core\Repositories\NotificationRepositoryInterface;
use kintai\Core\Request;
use kintai\Core\Response;
use kintai\UI\ViewRenderer;

final class NotificationController
{
    use HasBaseUrl;

    public function __construct(
        private readonly AuthService $auth,
        private readonly NotificationRepositoryInterface $repo,
        private readonly DevicePushTokenRepositoryInterface $pushTokens,
        private readonly ViewRenderer $view,
    ) {}

    private const PER_PAGE = 30;

    /** Nombre maximal de notifications renvoyées par un appel de poll (le curseur reprend la suite au suivant). */
    private const POLL_BATCH = 50;

    public function index(Request $request): Response
    {
        $userId     = (int) $this->auth->user()['id'];
        $total      = $this->repo->countByUser($userId);
        $totalPages = max(1, (int) ceil($total / self::PER_PAGE));
        $page       = min($totalPages, max(1, (int) $request->query('page', 1)));

        return Response::html($this->view->render('notifications.index', [
            'title'         => __('notifications'),
            'notifications' => $this->repo->findByUser($userId, self::PER_PAGE, ($page - 1) * self::PER_PAGE),
            'page'          => $page,
            'totalPages'    => $totalPages,
        ], 'layout.app'));
    }

    /**
     * GET /notifications/{id}/open
     * Marque la notification comme lue puis redirige vers sa destination (link) —
     * permet de cliquer une notification pour aller directement voir ce qu'elle
     * concerne (le shift, la demande...) au lieu de devoir le retrouver soi-même.
     * Sans destination stockée (notifications créées avant ce champ, ou type qui
     * n'en fournit pas), retombe simplement sur la liste des notifications.
     */
    public function open(Request $request): Response
    {
        $userId = (int) $this->auth->user()['id'];
        $id     = (int) $request->param('id');

        $notification = $this->repo->findById($id);
        if ($notification === null || (int) ($notification['user_id'] ?? 0) !== $userId) {
            throw new NotFoundException(__('error_resource_not_found'));
        }

        $this->repo->markRead($id, $userId);

        $link = (string) ($notification['link'] ?? '');
        if ($link === '' || !str_starts_with($link, '/') || str_starts_with($link, '//')) {
            $link = '/notifications';
        }

        return Response::redirect($this->base() . $link);
    }

    public function markRead(Request $request): Response
    {
        $userId = (int) $this->auth->user()['id'];
        $id     = (int) $request->param('id');
        $this->repo->markRead($id, $userId);

        if ($request->isAjax()) {
            return Response::json(['ok' => true]);
        }

        // Chemin interne uniquement : une destination absolue ou en `//` faisait de cette route une redirection ouverte.
        return Response::redirect(safe_redirect_path((string) $request->post('redirect', ''), $this->base() . '/notifications'));
    }

    public function markAllRead(Request $request): Response
    {
        $userId = (int) $this->auth->user()['id'];
        $this->repo->markAllRead($userId);

        if ($request->isAjax()) {
            return Response::json(['ok' => true]);
        }

        return Response::redirect($this->base() . '/notifications');
    }

    public function deleteAll(Request $request): Response
    {
        $userId = (int) $this->auth->user()['id'];
        $this->repo->deleteAllForUser($userId);

        if ($request->isAjax()) {
            return Response::json(['ok' => true]);
        }

        return Response::redirect($this->base() . '/notifications');
    }

    /**
     * POST /notifications/push-subscribe
     * Enregistre le jeton FCM obtenu par push.js pour ce navigateur (web push).
     * Distinct de l'API Bearer /api/v1/users/{id}/push-tokens : ici l'appelant
     * est le navigateur dans une session authentifiée, pas un client externe.
     */
    public function pushSubscribe(Request $request): Response
    {
        $userId = (int) $this->auth->user()['id'];
        $token  = trim((string) ($request->json('token') ?? ''));
        if ($token === '') {
            throw new ValidationException(['token' => [__('validation_device_token_required')]]);
        }

        $this->pushTokens->save([
            'user_id'  => $userId,
            'token'    => $token,
            'platform' => 'web',
        ]);

        return Response::json(['ok' => true]);
    }

    /**
     * POST /notifications/push-unsubscribe
     * Désenregistre le jeton (l'utilisateur a désactivé les notifications push
     * sur ce navigateur).
     */
    public function pushUnsubscribe(Request $request): Response
    {
        $userId = (int) $this->auth->user()['id'];
        $token  = trim((string) ($request->json('token') ?? ''));
        if ($token !== '') {
            $this->pushTokens->deleteByToken($token, $userId);
        }

        return Response::json(['ok' => true]);
    }

    /**
     * GET /notifications/poll?after_id=&count=
     * Appelé toutes les 15 s par notifications.js (un seul onglet à la fois). Le curseur est l'id de la dernière
     * notification vue, attribué par la base : ni l'horloge du navigateur ni la précision à la seconde n'entrent
     * en jeu, donc aucune notification perdue ni affichée deux fois.
     *
     * - notifications : nouvelles notifications NON LUES (pour les toasts) ;
     * - last_id       : nouveau curseur (lues comprises, pour ne pas les redemander) ;
     * - panel_html    : contenu du panneau de la cloche, seulement si quelque chose a changé (nouvelles
     *                   notifications, ou nombre de non lues différent de `count` connu du navigateur).
     */
    public function poll(Request $request): Response
    {
        $userId  = (int) $this->auth->user()['id'];
        $afterId = max(0, (int) $request->query('after_id', 0));
        $known   = (int) $request->query('count', -1);

        $new    = $this->repo->findAfterId($userId, $afterId, self::POLL_BATCH);
        $count  = $this->repo->countUnread($userId);
        $lastId = $new === [] ? $afterId : (int) end($new)['id'];

        $payload = [
            'notifications' => array_values(array_map(fn(array $n) => [
                'id'    => (int) $n['id'],
                'type'  => $n['type'] ?? '',
                'title' => notification_type_label($n['type'] ?? ''),
                'body'  => notification_body($n),
                'link'  => route_url('notifications.open', ['id' => (int) $n['id']]),
            ], array_filter($new, fn(array $n) => (int) ($n['is_read'] ?? 0) === 0))),
            'unread_count' => $count,
            'badge'        => notification_badge_label($count),
            'last_id'      => $lastId,
        ];

        if ($new !== [] || $known !== $count) {
            $payload['panel_html'] = $this->panelHtml($userId, $count);
        }

        return Response::json($payload);
    }

    /** Contenu du panneau de la cloche, même partiel que la topbar. */
    private function panelHtml(int $userId, int $unread): string
    {
        return $this->view->renderPartial('layout.partials._notif_panel', [
            'notif_items'  => $this->repo->findByUser($userId, 10),
            'notif_unread' => $unread,
        ]);
    }
}
