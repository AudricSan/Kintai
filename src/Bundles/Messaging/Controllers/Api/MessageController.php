<?php

declare(strict_types=1);

namespace kintai\Bundles\Messaging\Controllers\Api;

use kintai\Core\Exceptions\NotFoundException;
use kintai\Core\Repositories\MessageRepositoryInterface;
use kintai\Core\Request;
use kintai\Core\Response;

final class MessageController
{
    public function __construct(private readonly MessageRepositoryInterface $messages) {}

    // ─────────────────────────────────────────────────────────────────────────
    // Threads
    // ─────────────────────────────────────────────────────────────────────────

    /** GET /api/v1/messages/threads?user_id=X */
    public function listThreads(Request $request): Response
    {
        $userId = $request->query('user_id');
        if ($userId === null) {
            $authUser = $request->getAttribute('auth_user');
            $userId   = $authUser['id'] ?? 0;
        }

        $participations = $this->messages->findParticipationsByUser((int) $userId);
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
        $data   = $request->json() ?? [];
        $thread = $this->messages->saveThread(array_merge($data, ['created_at' => date('Y-m-d H:i:s')]));

        // Ajouter les participants si fournis
        foreach ((array) ($data['participant_ids'] ?? []) as $uid) {
            $this->messages->saveParticipant([
                'thread_id'  => $thread['id'],
                'user_id'    => (int) $uid,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        }

        return Response::json($thread, 201);
    }

    /** GET /api/v1/messages/threads/{id} */
    public function getThread(Request $request): Response
    {
        $thread = $this->messages->findThreadById((int) $request->param('id'));
        if ($thread === null) {
            throw new NotFoundException('Thread introuvable.');
        }
        return Response::json($thread);
    }

    /** DELETE /api/v1/messages/threads/{id} */
    public function deleteThread(Request $request): Response
    {
        $id = (int) $request->param('id');
        if ($this->messages->findThreadById($id) === null) {
            throw new NotFoundException('Thread introuvable.');
        }

        $this->messages->deleteMessagesByThread($id);
        $this->messages->deleteParticipantsByThread($id);
        $this->messages->deleteThread($id);

        return Response::empty();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Messages d'un thread
    // ─────────────────────────────────────────────────────────────────────────

    /** GET /api/v1/messages/threads/{id}/messages */
    public function listMessages(Request $request): Response
    {
        $threadId = (int) $request->param('id');
        if ($this->messages->findThreadById($threadId) === null) {
            throw new NotFoundException('Thread introuvable.');
        }
        return Response::json($this->messages->findMessagesByThread($threadId));
    }

    /** POST /api/v1/messages/threads/{id}/messages */
    public function addMessage(Request $request): Response
    {
        $threadId = (int) $request->param('id');
        if ($this->messages->findThreadById($threadId) === null) {
            throw new NotFoundException('Thread introuvable.');
        }

        $authUser = $request->getAttribute('auth_user');
        $data     = array_merge($request->json() ?? [], [
            'thread_id'  => $threadId,
            'sender_id'  => (int) ($authUser['id'] ?? 0),
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        return Response::json($this->messages->saveMessage($data), 201);
    }

    /** DELETE /api/v1/messages/{id} */
    public function deleteMessage(Request $request): Response
    {
        $id = (int) $request->param('id');
        if ($this->messages->findMessageById($id) === null) {
            throw new NotFoundException('Message introuvable.');
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
        if ($this->messages->findThreadById($threadId) === null) {
            throw new NotFoundException('Thread introuvable.');
        }
        return Response::json($this->messages->findParticipantsByThread($threadId));
    }

    /** POST /api/v1/messages/threads/{id}/participants */
    public function addParticipant(Request $request): Response
    {
        $threadId = (int) $request->param('id');
        if ($this->messages->findThreadById($threadId) === null) {
            throw new NotFoundException('Thread introuvable.');
        }

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
        $userId   = (int) $request->param('user_id');

        $participant = $this->messages->findParticipant($threadId, $userId);
        if ($participant === null) {
            throw new NotFoundException('Participant introuvable.');
        }

        return Response::json($participant);
    }
}
