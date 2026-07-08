<?php

declare(strict_types=1);

namespace kintai\UI\Controller\Api\V1;

use kintai\Core\Exceptions\NotFoundException;
use kintai\Core\Repositories\UserRepositoryInterface;
use kintai\Core\Repositories\UserShiftTypeRateRepositoryInterface;
use kintai\Core\Request;
use kintai\Core\Response;

final class UserShiftRateController
{
    public function __construct(
        private readonly UserShiftTypeRateRepositoryInterface $rates,
        private readonly UserRepositoryInterface $users,
    ) {}

    /** GET /api/v1/users/{user_id}/rates */
    public function index(Request $request): Response
    {
        $userId = (int) $request->param('user_id');
        $this->requireUser($userId);
        return Response::json($this->rates->findByUser($userId));
    }

    /** GET /api/v1/users/{user_id}/rates/{id} */
    public function show(Request $request): Response
    {
        $userId = (int) $request->param('user_id');
        $id     = (int) $request->param('id');
        $this->requireUser($userId);

        $rate = $this->rates->findById($id);
        if ($rate === null || (int) $rate['user_id'] !== $userId) {
            throw new NotFoundException('Taux introuvable.');
        }

        return Response::json($rate);
    }

    /** POST /api/v1/users/{user_id}/rates */
    public function store(Request $request): Response
    {
        $userId = (int) $request->param('user_id');
        $this->requireUser($userId);

        $data = array_merge($request->json() ?? [], [
            'user_id'    => $userId,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        return Response::json($this->rates->save($data), 201);
    }

    /** PUT /api/v1/users/{user_id}/rates/{id} */
    public function update(Request $request): Response
    {
        $userId = (int) $request->param('user_id');
        $id     = (int) $request->param('id');
        $this->requireUser($userId);

        $rate = $this->rates->findById($id);
        if ($rate === null || (int) $rate['user_id'] !== $userId) {
            throw new NotFoundException('Taux introuvable.');
        }

        return Response::json($this->rates->save(array_merge($request->json() ?? [], [
            'id'         => $id,
            'user_id'    => $userId,
            'updated_at' => date('Y-m-d H:i:s'),
        ])));
    }

    /** DELETE /api/v1/users/{user_id}/rates/{id} */
    public function destroy(Request $request): Response
    {
        $userId = (int) $request->param('user_id');
        $id     = (int) $request->param('id');
        $this->requireUser($userId);

        $rate = $this->rates->findById($id);
        if ($rate === null || (int) $rate['user_id'] !== $userId) {
            throw new NotFoundException('Taux introuvable.');
        }

        $this->rates->delete($id);
        return Response::empty();
    }

    private function requireUser(int $id): void
    {
        if ($this->users->findById($id) === null) {
            throw new NotFoundException('Utilisateur introuvable.');
        }
    }
}
