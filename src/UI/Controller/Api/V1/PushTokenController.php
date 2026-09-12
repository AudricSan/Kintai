<?php

declare(strict_types=1);

namespace kintai\UI\Controller\Api\V1;

use kintai\Core\Exceptions\NotFoundException;
use kintai\Core\Exceptions\ValidationException;
use kintai\Core\Repositories\DevicePushTokenRepositoryInterface;
use kintai\Core\Repositories\UserRepositoryInterface;
use kintai\Core\Request;
use kintai\Core\Response;

final class PushTokenController
{
    private const PLATFORMS = ['android', 'ios', 'web'];

    public function __construct(
        private readonly DevicePushTokenRepositoryInterface $tokens,
        private readonly UserRepositoryInterface $users,
    ) {}

    /**
     * POST /api/v1/users/{user_id}/push-tokens
     * Enregistre (ou réassigne) un jeton FCM pour cet utilisateur. Un même jeton peut
     * migrer d'un utilisateur à l'autre sur un appareil partagé (ex. tablette de
     * magasin passée de main en main entre employés) : upsert par jeton, pas par user.
     */
    public function store(Request $request): Response
    {
        $userId = (int) $request->param('user_id');
        $this->requireUser($userId);

        $data     = $request->json() ?? [];
        $token    = trim((string) ($data['token'] ?? ''));
        $platform = (string) ($data['platform'] ?? '');

        if ($token === '') {
            throw new ValidationException(['token' => ['Le jeton de l\'appareil est requis.']]);
        }
        if ($platform !== '' && !in_array($platform, self::PLATFORMS, true)) {
            throw new ValidationException(['platform' => ['Plateforme invalide (android, ios ou web).']]);
        }

        $record = $this->tokens->save([
            'user_id'  => $userId,
            'token'    => $token,
            'platform' => $platform ?: null,
        ]);

        return Response::json($record, 201);
    }

    /**
     * DELETE /api/v1/users/{user_id}/push-tokens
     * Body : { "token": "..." } — désenregistre un appareil (déconnexion, désinstallation).
     */
    public function destroy(Request $request): Response
    {
        $userId = (int) $request->param('user_id');
        $this->requireUser($userId);

        $token = trim((string) ($request->json('token') ?? ''));
        if ($token === '') {
            throw new ValidationException(['token' => ['Le jeton de l\'appareil est requis.']]);
        }

        $this->tokens->deleteByToken($token);
        return Response::empty();
    }

    private function requireUser(int $id): void
    {
        if ($this->users->findById($id) === null) {
            throw new NotFoundException('Utilisateur introuvable.');
        }
    }
}
