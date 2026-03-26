<?php

declare(strict_types=1);

namespace kintai\UI\Controller\Api;

use kintai\Core\Exceptions\NotFoundException;
use kintai\Core\Repositories\ShiftSwapRequestRepositoryInterface;
use kintai\Core\Request;
use kintai\Core\Response;

final class ShiftSwapRequestController
{
    public function __construct(private readonly ShiftSwapRequestRepositoryInterface $swapRequests) {}

    /**
     * GET /api/shift-swap-requests
     * Paramètres query optionnels : store_id, requester_id, status
     */
    public function index(Request $request): Response
    {
        $storeId     = $request->query('store_id');
        $requesterId = $request->query('requester_id');
        $status      = $request->query('status');

        if ($storeId !== null && $status !== null) {
            return Response::json($this->swapRequests->findByStatus((int) $storeId, $status));
        }
        if ($storeId !== null) {
            return Response::json($this->swapRequests->findByStore((int) $storeId));
        }
        if ($requesterId !== null) {
            return Response::json($this->swapRequests->findByRequester((int) $requesterId));
        }

        return Response::json([]);
    }

    /** GET /api/shift-swap-requests/{id} */
    public function show(Request $request): Response
    {
        $record = $this->swapRequests->findById((int) $request->param('id'));
        if ($record === null) {
            throw new NotFoundException('Demande d\'échange introuvable.');
        }
        return Response::json($record);
    }

    /** POST /api/shift-swap-requests */
    public function store(Request $request): Response
    {
        $data = $request->json() ?? [];
        $saved = $this->swapRequests->save($data);
        return Response::json($saved, 201);
    }

    /** PUT /api/shift-swap-requests/{id} */
    public function update(Request $request): Response
    {
        $id = (int) $request->param('id');
        if ($this->swapRequests->findById($id) === null) {
            throw new NotFoundException('Demande d\'échange introuvable.');
        }
        $data = array_merge($request->json() ?? [], ['id' => $id]);
        return Response::json($this->swapRequests->save($data));
    }

    /** DELETE /api/shift-swap-requests/{id} */
    public function destroy(Request $request): Response
    {
        $id = (int) $request->param('id');
        if ($this->swapRequests->findById($id) === null) {
            throw new NotFoundException('Demande d\'échange introuvable.');
        }
        $this->swapRequests->delete($id);
        return Response::empty();
    }
}
