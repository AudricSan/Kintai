<?php

declare(strict_types=1);

namespace kintai\UI\Controller\Api\V1;

use kintai\Core\Exceptions\NotFoundException;
use kintai\Core\Repositories\IcalTokenRepositoryInterface;
use kintai\Core\Repositories\UserRepositoryInterface;
use kintai\Core\Request;
use kintai\Core\Response;

final class IcalTokenController
{
    public function __construct(
        private readonly IcalTokenRepositoryInterface $icalTokens,
        private readonly UserRepositoryInterface $users,
    ) {}

    /** GET /api/v1/users/{user_id}/ical-tokens */
    public function index(Request $request): Response
    {
        $userId = (int) $request->param('user_id');
        $this->requireUser($userId);
        return Response::json($this->icalTokens->findByUser($userId));
    }

    /** GET /api/v1/users/{user_id}/ical-tokens/{store_id} */
    public function show(Request $request): Response
    {
        $userId  = (int) $request->param('user_id');
        $storeId = (int) $request->param('store_id');
        $this->requireUser($userId);

        $token = $this->icalTokens->findByUserAndStore($userId, $storeId);
        if ($token === null) {
            throw new NotFoundException('Token iCal introuvable.');
        }

        return Response::json($token);
    }

    /** POST /api/v1/users/{user_id}/ical-tokens */
    public function store(Request $request): Response
    {
        $userId  = (int) $request->param('user_id');
        $this->requireUser($userId);

        $data    = $request->json() ?? [];
        $storeId = (int) ($data['store_id'] ?? 0);

        $saved = $this->icalTokens->save([
            'user_id'    => $userId,
            'store_id'   => $storeId,
            'token'      => bin2hex(random_bytes(32)),
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        return Response::json($saved, 201);
    }

    /** DELETE /api/v1/users/{user_id}/ical-tokens/{store_id} */
    public function destroy(Request $request): Response
    {
        $userId  = (int) $request->param('user_id');
        $storeId = (int) $request->param('store_id');
        $this->requireUser($userId);

        if ($this->icalTokens->findByUserAndStore($userId, $storeId) === null) {
            throw new NotFoundException('Token iCal introuvable.');
        }

        $this->icalTokens->deleteByUserAndStore($userId, $storeId);
        return Response::empty();
    }

    /** POST /api/v1/users/{user_id}/ical-tokens/{store_id}/regenerate */
    public function regenerate(Request $request): Response
    {
        $userId  = (int) $request->param('user_id');
        $storeId = (int) $request->param('store_id');
        $this->requireUser($userId);

        $existing = $this->icalTokens->findByUserAndStore($userId, $storeId);
        $saved    = $this->icalTokens->save(array_merge($existing ?? [], [
            'user_id'    => $userId,
            'store_id'   => $storeId,
            'token'      => bin2hex(random_bytes(32)),
            'updated_at' => date('Y-m-d H:i:s'),
            'created_at' => $existing['created_at'] ?? date('Y-m-d H:i:s'),
        ]));

        return Response::json($saved);
    }

    private function requireUser(int $id): void
    {
        if ($this->users->findById($id) === null) {
            throw new NotFoundException('Utilisateur introuvable.');
        }
    }
}
