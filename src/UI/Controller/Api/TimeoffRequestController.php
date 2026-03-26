<?php

declare(strict_types=1);

namespace kintai\UI\Controller\Api;

use kintai\Core\Exceptions\NotFoundException;
use kintai\Core\Repositories\TimeoffRequestRepositoryInterface;
use kintai\Core\Request;
use kintai\Core\Response;

final class TimeoffRequestController
{
    public function __construct(private readonly TimeoffRequestRepositoryInterface $timeoffRequests) {}

    /**
     * GET /api/timeoff-requests
     * Paramètres query optionnels : store_id, user_id, status
     */
    public function index(Request $request): Response
    {
        $storeId = $request->query('store_id');
        $userId  = $request->query('user_id');
        $status  = $request->query('status');

        if ($storeId !== null && $status !== null) {
            return Response::json($this->timeoffRequests->findByStatus((int) $storeId, $status));
        }
        if ($storeId !== null) {
            return Response::json($this->timeoffRequests->findByStore((int) $storeId));
        }
        if ($userId !== null) {
            return Response::json($this->timeoffRequests->findByUser((int) $userId));
        }

        return Response::json([]);
    }

    /** GET /api/timeoff-requests/{id} */
    public function show(Request $request): Response
    {
        $record = $this->timeoffRequests->findById((int) $request->param('id'));
        if ($record === null) {
            throw new NotFoundException('Demande de congé introuvable.');
        }
        return Response::json($record);
    }

    /** POST /api/timeoff-requests */
    public function store(Request $request): Response
    {
        $data = $request->json() ?? [];
        $saved = $this->timeoffRequests->save($data);
        return Response::json($saved, 201);
    }

    /** PUT /api/timeoff-requests/{id} */
    public function update(Request $request): Response
    {
        $id = (int) $request->param('id');
        if ($this->timeoffRequests->findById($id) === null) {
            throw new NotFoundException('Demande de congé introuvable.');
        }
        $data = array_merge($request->json() ?? [], ['id' => $id]);
        return Response::json($this->timeoffRequests->save($data));
    }

    /** DELETE /api/timeoff-requests/{id} */
    public function destroy(Request $request): Response
    {
        $id = (int) $request->param('id');
        if ($this->timeoffRequests->findById($id) === null) {
            throw new NotFoundException('Demande de congé introuvable.');
        }
        $this->timeoffRequests->delete($id);
        return Response::empty();
    }
}
