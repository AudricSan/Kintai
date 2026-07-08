<?php

declare(strict_types=1);

namespace kintai\Bundles\Messaging\Controllers\Web;

use kintai\Core\Exceptions\ForbiddenException;
use kintai\Core\Exceptions\NotFoundException;
use kintai\Core\Repositories\MessageRepositoryInterface;
use kintai\Core\Repositories\StoreRepositoryInterface;
use kintai\Core\Repositories\StoreUserRepositoryInterface;
use kintai\Core\Repositories\UserRepositoryInterface;
use kintai\Core\Request;
use kintai\Core\Response;
use kintai\Core\Services\AuditLogger;
use kintai\Core\Services\NotificationService;
use kintai\UI\ViewRenderer;

/**
 * Messagerie interne — partagée entre les routes admin/manager (/admin/messages)
 * et employé (/employee/messages), qui se distinguent uniquement par le chemin
 * de la requête (cf. isAdminContext()) : l'employé est restreint à ses propres
 * stores, là où un manager/admin peut voir ses stores gérés / tous les stores.
 */
final class MessageController
{
    public function __construct(
        private readonly ViewRenderer $view,
        private readonly UserRepositoryInterface $users,
        private readonly StoreRepositoryInterface $stores,
        private readonly StoreUserRepositoryInterface $storeUsers,
        private readonly MessageRepositoryInterface $msgRepo,
        private readonly AuditLogger $auditLogger,
        private readonly NotificationService $notifs,
    ) {}

    public function messages(Request $request): Response
    {
        $urlPrefix = $this->urlPrefix($request);
        if (!$this->isAdminContext($request)) {
            $this->assertFeature($request, 'messages');
        }

        $authUser   = $request->getAttribute('auth_user');
        $userId     = (int) $authUser['id'];
        $tab        = $request->query('tab', 'inbox');
        $managedIds = $this->managedIds($request);

        $participations = $this->msgRepo->findParticipationsByUser($userId);
        $readMap        = [];
        foreach ($participations as $p) {
            $readMap[(int) $p['thread_id']] = (int) $p['is_read'];
        }

        $threads = [];
        foreach ($participations as $p) {
            $tid    = (int) $p['thread_id'];
            $thread = $this->msgRepo->findThreadById($tid);
            if ($thread === null) {
                continue;
            }
            if ($managedIds !== null && !in_array((int) $thread['store_id'], $managedIds, true)) {
                continue;
            }
            $msgs = $this->msgRepo->findMessagesByThread($tid);
            usort($msgs, fn($a, $b) => strcmp($a['sent_at'], $b['sent_at']));
            $last = count($msgs) > 0 ? end($msgs) : null;

            $threads[$tid] = array_merge($thread, [
                '_is_read'   => $readMap[$tid] ?? 1,
                '_last_msg'  => $last,
                '_msg_count' => count($msgs),
            ]);
        }
        $threads = array_values($threads);

        usort($threads, function ($a, $b) {
            $ta = $a['_last_msg']['sent_at'] ?? $a['created_at'];
            $tb = $b['_last_msg']['sent_at'] ?? $b['created_at'];
            return strcmp($tb, $ta);
        });

        if ($tab === 'sent') {
            $threads = array_values(array_filter($threads, fn($t) => (int) $t['creator_id'] === $userId));
        } elseif ($tab === 'unread') {
            $threads = array_values(array_filter($threads, fn($t) => $t['_is_read'] === 0));
        }

        $usersMap = $this->buildUsersMap();
        $storesMap = $this->buildStoresMap();

        return Response::html($this->view->render('messaging::messages', [
            'threads'    => $threads,
            'users_map'  => $usersMap,
            'stores_map' => $storesMap,
            'tab'        => $tab,
            'user_id'    => $userId,
            'base_path'  => $urlPrefix,
        ], 'layout.app'));
    }

    public function messagesCompose(Request $request): Response
    {
        $urlPrefix  = $this->urlPrefix($request);
        $authUser   = $request->getAttribute('auth_user');
        $managedIds = $this->managedIds($request);
        $stores     = $this->availableStores($managedIds);

        if ($managedIds !== null) {
            $memberIds = $this->memberUserIds($managedIds);
            $users     = array_values(array_filter(
                $this->users->findAll(),
                fn($u) => in_array((int) $u['id'], $memberIds, true) && (int) $u['id'] !== (int) $authUser['id']
            ));
        } else {
            $users = array_values(array_filter(
                $this->users->findAll(),
                fn($u) => (int) $u['id'] !== (int) $authUser['id']
            ));
        }

        return Response::html($this->view->render('messaging::messages-compose', [
            'all_stores' => $stores,
            'all_users'  => $users,
            'base_path'  => $urlPrefix,
        ], 'layout.app'));
    }

    public function messagesSend(Request $request): Response
    {
        $urlPrefix = $this->urlPrefix($request);
        if (!$this->isAdminContext($request)) {
            $this->assertFeature($request, 'messages');
        }

        $authUser   = $request->getAttribute('auth_user');
        $userId     = (int) $authUser['id'];
        $storeId    = (int) $request->post('store_id', 0);
        $subject    = trim((string) $request->post('subject', ''));
        $body       = trim((string) $request->post('body', ''));
        $recipients = array_values(array_unique(array_filter(
            array_map('intval', (array) $request->post('recipient_ids', [])),
            fn($rid) => $rid > 0 && $rid !== $userId
        )));

        if ($storeId === 0 || $subject === '' || $body === '' || empty($recipients)) {
            return Response::redirect($this->base() . $urlPrefix . '/compose?error=missing_fields');
        }
        $this->assertStoreAccess($request, $storeId);

        $now    = date('Y-m-d H:i:s');
        $thread = $this->msgRepo->saveThread([
            'store_id'   => $storeId,
            'creator_id' => $userId,
            'subject'    => $subject,
            'created_at' => $now,
        ]);
        $threadId = (int) $thread['id'];

        $this->msgRepo->saveMessage([
            'thread_id' => $threadId,
            'sender_id' => $userId,
            'body'      => $body,
            'sent_at'   => $now,
        ]);

        $this->msgRepo->saveParticipant(['thread_id' => $threadId, 'user_id' => $userId, 'is_read' => 1]);
        foreach ($recipients as $rid) {
            $this->msgRepo->saveParticipant(['thread_id' => $threadId, 'user_id' => $rid, 'is_read' => 0]);
        }

        $this->notifs->notifyMany(
            $recipients,
            'message_received',
            'Nouveau message : ' . mb_strimwidth($subject, 0, 60, '…'),
            $threadId
        );

        $this->auditLogger->log($request, 'message.sent', 'message_thread', $threadId, [
            'store_id'    => $storeId,
            'subject'     => $subject,
            'recipients'  => $recipients,
        ], $storeId);

        return Response::redirect($this->base() . $urlPrefix . '/' . $threadId . '?success=sent');
    }

    public function messagesThread(Request $request): Response
    {
        $urlPrefix = $this->urlPrefix($request);
        $authUser  = $request->getAttribute('auth_user');
        $userId    = (int) $authUser['id'];
        $threadId  = (int) $request->param('id');

        $thread = $this->msgRepo->findThreadById($threadId);
        if ($thread === null) {
            throw new NotFoundException('Conversation introuvable.');
        }
        $this->assertStoreAccess($request, (int) $thread['store_id']);

        $part = $this->msgRepo->findParticipant($threadId, $userId);
        if ($part === null) {
            throw new ForbiddenException('Vous ne faites pas partie de cette conversation.');
        }
        if (!(int) ($part['is_read'] ?? 0)) {
            $this->msgRepo->saveParticipant(array_merge($part, ['is_read' => 1]));
        }

        $msgs = $this->msgRepo->findMessagesByThread($threadId);
        usort($msgs, fn($a, $b) => strcmp($a['sent_at'], $b['sent_at']));

        $usersMap = $this->buildUsersMap();
        $storesMap = $this->buildStoresMap();

        return Response::html($this->view->render('messaging::messages-thread', [
            'thread'       => $thread,
            'messages'     => $msgs,
            'participants' => $this->msgRepo->findParticipantsByThread($threadId),
            'users_map'    => $usersMap,
            'stores_map'   => $storesMap,
            'user_id'      => $userId,
            'base_path'    => $urlPrefix,
            'can_manage'   => $this->isAdminContext($request),
        ], 'layout.app'));
    }

    public function messagesReply(Request $request): Response
    {
        $urlPrefix = $this->urlPrefix($request);
        $authUser  = $request->getAttribute('auth_user');
        $userId    = (int) $authUser['id'];
        $threadId  = (int) $request->param('id');
        $body      = trim((string) $request->post('body', ''));

        if ($body === '') {
            return Response::redirect($this->base() . $urlPrefix . '/' . $threadId);
        }

        $thread = $this->msgRepo->findThreadById($threadId);
        if ($thread === null) {
            throw new NotFoundException('Conversation introuvable.');
        }
        $this->assertStoreAccess($request, (int) $thread['store_id']);

        $part = $this->msgRepo->findParticipant($threadId, $userId);
        if ($part === null) {
            throw new ForbiddenException('Vous ne faites pas partie de cette conversation.');
        }

        $this->msgRepo->saveMessage([
            'thread_id' => $threadId,
            'sender_id' => $userId,
            'body'      => $body,
            'sent_at'   => date('Y-m-d H:i:s'),
        ]);

        $otherParticipants = [];
        foreach ($this->msgRepo->findParticipantsByThread($threadId) as $p) {
            if ((int) $p['user_id'] !== $userId) {
                $this->msgRepo->saveParticipant(array_merge($p, ['is_read' => 0]));
                $otherParticipants[] = (int) $p['user_id'];
            }
        }

        if (!empty($otherParticipants)) {
            $this->notifs->notifyMany(
                $otherParticipants,
                'message_received',
                'Nouveau message dans : ' . mb_strimwidth($thread['subject'] ?? '', 0, 60, '…'),
                $threadId
            );
        }

        $this->auditLogger->log($request, 'message.replied', 'message_thread', $threadId, [
            'store_id' => $thread['store_id'] ?? null,
        ], (int) ($thread['store_id'] ?? 0) ?: null);

        if ($request->isAjax()) {
            return Response::json(['ok' => true]);
        }
        return Response::redirect($this->base() . $urlPrefix . '/' . $threadId . '?success=replied');
    }

    public function messagesDeleteThread(Request $request): Response
    {
        $urlPrefix = $this->urlPrefix($request);
        $authUser  = $request->getAttribute('auth_user');
        $userId    = (int) $authUser['id'];
        $threadId  = (int) $request->param('id');

        $thread = $this->msgRepo->findThreadById($threadId);
        if ($thread === null) {
            throw new NotFoundException('Conversation introuvable.');
        }
        $this->assertStoreAccess($request, (int) $thread['store_id']);

        $part = $this->msgRepo->findParticipant($threadId, $userId);
        if ($part === null) {
            throw new ForbiddenException('Vous ne faites pas partie de cette conversation.');
        }

        $storeId = (int) ($thread['store_id'] ?? 0);
        $this->auditLogger->log($request, 'message.thread_deleted', 'message_thread', $threadId, [
            'store_id' => $storeId ?: null,
            'subject'  => $thread['subject'] ?? '',
        ], $storeId ?: null);

        $this->msgRepo->deleteMessagesByThread($threadId);
        $this->msgRepo->deleteParticipantsByThread($threadId);
        $this->msgRepo->deleteThread($threadId);

        return Response::redirect($this->base() . $urlPrefix . '?success=deleted');
    }

    public function messagesDeleteMessage(Request $request): Response
    {
        $urlPrefix = $this->urlPrefix($request);
        $authUser  = $request->getAttribute('auth_user');
        $userId    = (int) $authUser['id'];
        $threadId  = (int) $request->param('id');
        $mid       = (int) $request->param('mid');

        $thread = $this->msgRepo->findThreadById($threadId);
        if ($thread === null) {
            throw new NotFoundException('Conversation introuvable.');
        }
        $this->assertStoreAccess($request, (int) $thread['store_id']);

        $message = $this->msgRepo->findMessageById($mid);
        if ($message === null || (int) $message['thread_id'] !== $threadId) {
            throw new NotFoundException('Message introuvable.');
        }
        if ((int) $message['sender_id'] !== $userId) {
            throw new ForbiddenException('Vous ne pouvez supprimer que vos propres messages.');
        }

        $this->msgRepo->deleteMessage($mid);
        $this->auditLogger->log($request, 'message.deleted', 'message', $mid, [
            'thread_id' => $threadId,
            'store_id'  => $thread['store_id'] ?? null,
        ], (int) ($thread['store_id'] ?? 0) ?: null);

        if ($request->isAjax()) {
            return Response::json(['ok' => true]);
        }
        return Response::redirect($this->base() . $urlPrefix . '/' . $threadId);
    }

    // --- Private helpers ---

    /**
     * Contexte admin/manager (route /admin/messages*) vs employé (/employee/messages*).
     */
    private function isAdminContext(Request $request): bool
    {
        return str_starts_with($request->uri(), '/admin/');
    }

    private function urlPrefix(Request $request): string
    {
        return $this->isAdminContext($request) ? '/admin/messages' : '/employee/messages';
    }

    /**
     * Stores accessibles pour filtrer les fils/destinataires :
     * - null            → admin global, aucune restriction
     * - int[]           → manager (stores gérés) ou employé (ses propres stores)
     */
    private function managedIds(Request $request): ?array
    {
        $user = $request->getAttribute('auth_user');
        if (!empty($user['is_admin'])) {
            return null;
        }

        $managed = $request->getAttribute('managed_store_ids');
        if ($managed !== null) {
            return $managed;
        }

        // Employé simple (pas passé par AdminMiddleware) : restreint à ses propres stores.
        $userId = (int) ($user['id'] ?? 0);
        return array_values(array_unique(array_map(
            fn($su) => (int) $su['store_id'],
            $this->storeUsers->findByUser($userId)
        )));
    }

    private function availableStores(?array $managedIds): array
    {
        $all = $this->stores->findAll();
        if ($managedIds === null) {
            return $all;
        }
        return array_values(array_filter($all, fn($s) => in_array((int) $s['id'], $managedIds, true)));
    }

    private function memberUserIds(array $managedIds): array
    {
        $ids = [];
        foreach ($managedIds as $storeId) {
            foreach ($this->storeUsers->findByStore($storeId) as $m) {
                $ids[] = (int) $m['user_id'];
            }
        }
        return array_unique($ids);
    }

    private function base(): string
    {
        $sn   = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/index.php');
        $base = rtrim(str_replace('\\', '/', dirname($sn)), '/');
        return ($base === '.' || $base === '/') ? '' : $base;
    }

    private function assertStoreAccess(Request $request, int $storeId): void
    {
        $managedIds = $this->managedIds($request);
        if ($managedIds !== null && !in_array($storeId, $managedIds, true)) {
            throw new ForbiddenException('Vous n\'êtes pas autorisé pour ce store.');
        }
    }

    /**
     * Feature "messages" activable/désactivable par store (contexte employé uniquement ;
     * un admin/manager n'est jamais bloqué par ce toggle).
     */
    private function assertFeature(Request $request, string $feature): void
    {
        $user        = $request->getAttribute('auth_user');
        $memberships = $this->storeUsers->findByUser((int) ($user['id'] ?? 0));
        if (empty($memberships)) {
            return;
        }
        $features = $this->stores->getFeatures((int) $memberships[0]['store_id']);
        if ($features === []) {
            return;
        }
        if (!in_array($feature, $features, true)) {
            throw new ForbiddenException('feature_disabled');
        }
    }

    private function buildUsersMap(): array
    {
        $map = [];
        foreach ($this->users->findAll() as $u) {
            $map[(int) $u['id']] = $u['display_name'] ?? $u['email'] ?? '';
        }
        return $map;
    }

    private function buildStoresMap(): array
    {
        $map = [];
        foreach ($this->stores->findAll() as $s) {
            $map[(int) $s['id']] = $s['name'] ?? '';
        }
        return $map;
    }
}
