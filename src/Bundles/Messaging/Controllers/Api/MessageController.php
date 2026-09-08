<?php

declare(strict_types=1);

namespace kintai\Bundles\Messaging\Controllers\Api;

use kintai\Core\Exceptions\ForbiddenException;
use kintai\Core\Exceptions\NotFoundException;
use kintai\Core\Repositories\MessageRepositoryInterface;
use kintai\Core\Request;
use kintai\Core\Response;

/**
 * Messagerie API — strictement limitée aux conversations dont le porteur du
 * token est participant (voir .wiki/API-Reference.md : ces routes sont
 * volontairement absentes de config/api-permissions.php, en libre-service
 * comme notifications.*, mais bornées ici par appartenance plutôt que par
 * RBAC — un rôle n'a jamais besoin de porter les messages d'un tiers).
 */
final class MessageController
{
    public function __construct(private readonly MessageRepositoryInterface $messages) {}

    // ─────────────────────────────────────────────────────────────────────────
    // Threads
    // ─────────────────────────────────────────────────────────────────────────

    /** GET /api/v1/messages/threads */
    public function listThreads(Request $request): Response
    {
        $userId = $this->authUserId($request);

        $participations = $this->messages->findParticipationsByUser($userId);
        $threads        = [];
        foreach ($participations as $p) {
            $thread = $this->messages->findThreadById((int) $p['thread_id']);
            if ($thread !== null) {
                $threads[] = $thread;
            }
        }

        return Response::json($threads);
    }

    /** POST /api/v1/messages/threads */
    public function createThread(Request $request): Response
    {
        $userId = $this->authUserId($request);
        $data   = $request->json() ?? [];

        $thread = $this->messages->saveThread([
            'store_id'   => $data['store_id'] ?? null,
            'subject'    => $data['subject'] ?? null,
            'creator_id' => $userId,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        $threadId = (int) $thread['id'];

        $this->messages->saveParticipant([
            'thread_id'  => $threadId,
            'user_id'    => $userId,
            'is_read'    => 1,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        $participantIds = array_unique(array_filter(
            array_map('intval', (array) ($data['participant_ids'] ?? [])),
            fn($uid) => $uid > 0 && $uid !== $userId
        ));
        foreach ($participantIds as $uid) {
            $this->messages->saveParticipant([
                'thread_id'  => $threadId,
                'user_id'    => $uid,
                'is_read'    => 0,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        }

        return Response::json($thread, 201);
    }

    /** GET /api/v1/messages/threads/{id} */
    public function getThread(Request $request): Response
    {
        $threadId = (int) $request->param('id');
        $thread   = $this->requireThreadParticipant($request, $threadId);
        return Response::json($thread);
    }

    /** DELETE /api/v1/messages/threads/{id} */
    public function deleteThread(Request $request): Response
    {
        $threadId = (int) $request->param('id');
        $this->requireThreadParticipant($request, $threadId);

        $this->messages->deleteMessagesByThread($threadId);
        $this->messages->deleteParticipantsByThread($threadId);
        $this->messages->deleteThread($threadId);

        return Response::empty();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Messages d'un thread
    // ─────────────────────────────────────────────────────────────────────────

    /** GET /api/v1/messages/threads/{id}/messages */
    public function listMessages(Request $request): Response
    {
        $threadId = (int) $request->param('id');
        $this->requireThreadParticipant($request, $threadId);
        return Response::json($this->messages->findMessagesByThread($threadId));
    }

    /** POST /api/v1/messages/threads/{id}/messages */
    public function addMessage(Request $request): Response
    {
        $threadId = (int) $request->param('id');
        $userId   = $this->requireThreadParticipantId($request, $threadId);

        $data = array_merge($request->json() ?? [], [
            'thread_id'  => $threadId,
            'sender_id'  => $userId,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        return Response::json($this->messages->saveMessage($data), 201);
    }

    /** DELETE /api/v1/messages/{id} */
    public function deleteMessage(Request $request): Response
    {
        $userId = $this->authUserId($request);
        $id     = (int) $request->param('id');

        $message = $this->messages->findMessageById($id);
        if ($message === null) {
            throw new NotFoundException('Message introuvable.');
        }
        if ((int) $message['sender_id'] !== $userId) {
            throw new ForbiddenException('Vous ne pouvez supprimer que vos propres messages.');
        }

        $this->messages->deleteMessage($id);
        return Response::empty();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Participants
    // ─────────────────────────────────────────────────────────────────────────

    /** GET /api/v1/messages/threads/{id}/participants */
    public function listParticipants(Request $request): Response
    {
        $threadId = (int) $request->param('id');
        $this->requireThreadParticipant($request, $threadId);
        return Response::json($this->messages->findParticipantsByThread($threadId));
    }

    /** POST /api/v1/messages/threads/{id}/participants */
    public function addParticipant(Request $request): Response
    {
        $threadId = (int) $request->param('id');
        $this->requireThreadParticipant($request, $threadId);

        $data = array_merge($request->json() ?? [], [
            'thread_id'  => $threadId,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        return Response::json($this->messages->saveParticipant($data), 201);
    }

    /** GET /api/v1/messages/threads/{id}/participants/{user_id} */
    public function getParticipant(Request $request): Response
    {
        $threadId = (int) $request->param('id');
        $this->requireThreadParticipant($request, $threadId);

        $userId      = (int) $request->param('user_id');
        $participant = $this->messages->findParticipant($threadId, $userId);
        if ($participant === null) {
            throw new NotFoundException('Participant introuvable.');
        }

        return Response::json($participant);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────────

    private function authUserId(Request $request): int
    {
        $authUser = $request->getAttribute('auth_user');
        return (int) ($authUser['id'] ?? 0);
    }

    /**
     * Charge le thread et vérifie que le porteur du token en est participant.
     * Un thread introuvable et un thread dont on n'est pas participant
     * renvoient tous deux 404, pour ne pas révéler l'existence d'une
     * conversation à laquelle l'appelant est étranger.
     */
    private function requireThreadParticipant(Request $request, int $threadId): array
    {
        $thread = $this->messages->findThreadById($threadId);
        if ($thread === null) {
            throw new NotFoundException('Thread introuvable.');
        }
        $userId = $this->authUserId($request);
        if ($this->messages->findParticipant($threadId, $userId) === null) {
            throw new NotFoundException('Thread introuvable.');
        }
        return $thread;
    }

    private function requireThreadParticipantId(Request $request, int $threadId): int
    {
        $this->requireThreadParticipant($request, $threadId);
        return $this->authUserId($request);
    }
}
