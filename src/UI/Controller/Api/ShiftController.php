<?php

declare(strict_types=1);

namespace kintai\UI\Controller\Api;

use kintai\Core\Exceptions\NotFoundException;
use kintai\Core\Repositories\ShiftRepositoryInterface;
use kintai\Core\Request;
use kintai\Core\Response;

final class ShiftController
{
    public function __construct(private readonly ShiftRepositoryInterface $shifts) {}

    /**
     * GET /api/shifts
     * Paramètres query optionnels : store_id, user_id, date (YYYY-MM-DD)
     */
    public function index(Request $request): Response
    {
        $storeId = $request->query('store_id');
        $userId  = $request->query('user_id');
        $date    = $request->query('date');

        if ($storeId !== null && $date !== null) {
            return Response::json($this->shifts->findByDate((int) $storeId, $date));
        }
        if ($storeId !== null) {
            return Response::json($this->shifts->findByStore((int) $storeId));
        }
        if ($userId !== null) {
            return Response::json($this->shifts->findByUser((int) $userId));
        }

        return Response::json([]);
    }

    /** GET /api/shifts/{id} */
    public function show(Request $request): Response
    {
        $shift = $this->shifts->findById((int) $request->param('id'));
        if ($shift === null) {
            throw new NotFoundException('Shift introuvable.');
        }
        return Response::json($shift);
    }

    /** POST /api/shifts */
    public function store(Request $request): Response
    {
        $data = $request->json() ?? [];
        $saved = $this->shifts->save($data);
        return Response::json($saved, 201);
    }

    /** PUT /api/shifts/{id} */
    public function update(Request $request): Response
    {
        $id = (int) $request->param('id');
        if ($this->shifts->findById($id) === null) {
            throw new NotFoundException('Shift introuvable.');
        }
        $data = array_merge($request->json() ?? [], ['id' => $id]);
        return Response::json($this->shifts->save($data));
    }

    /** DELETE /api/shifts/{id} */
    public function destroy(Request $request): Response
    {
        $id = (int) $request->param('id');
        if ($this->shifts->findById($id) === null) {
            throw new NotFoundException('Shift introuvable.');
        }
        $this->shifts->delete($id);
        return Response::empty();
    }
}
