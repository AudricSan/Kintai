<?php

declare(strict_types=1);

namespace kintai\UI\Controller\Api;

use kintai\Core\Exceptions\NotFoundException;
use kintai\Core\Repositories\AvailabilityRepositoryInterface;
use kintai\Core\Request;
use kintai\Core\Response;

final class AvailabilityController
{
    public function __construct(private readonly AvailabilityRepositoryInterface $availabilities) {}

    /**
     * GET /api/availabilities
     * Paramètres query optionnels : store_id, user_id
     */
    public function index(Request $request): Response
    {
        $storeId = $request->query('store_id');
        $userId  = $request->query('user_id');

        if ($userId !== null) {
            return Response::json($this->availabilities->findByUser((int) $userId));
        }
        if ($storeId !== null) {
            return Response::json($this->availabilities->findByStore((int) $storeId));
        }

        return Response::json([]);
    }

    /** GET /api/availabilities/{id} */
    public function show(Request $request): Response
    {
        $availability = $this->availabilities->findById((int) $request->param('id'));
        if ($availability === null) {
            throw new NotFoundException('Disponibilité introuvable.');
        }
        return Response::json($availability);
    }

    /** POST /api/availabilities */
    public function store(Request $request): Response
    {
        $data = $request->json() ?? [];
        $saved = $this->availabilities->save($data);
        return Response::json($saved, 201);
    }

    /** PUT /api/availabilities/{id} */
    public function update(Request $request): Response
    {
        $id = (int) $request->param('id');
        if ($this->availabilities->findById($id) === null) {
            throw new NotFoundException('Disponibilité introuvable.');
        }
        $data = array_merge($request->json() ?? [], ['id' => $id]);
        return Response::json($this->availabilities->save($data));
    }

    /** DELETE /api/availabilities/{id} */
    public function destroy(Request $request): Response
    {
        $id = (int) $request->param('id');
        if ($this->availabilities->findById($id) === null) {
            throw new NotFoundException('Disponibilité introuvable.');
        }
        $this->availabilities->delete($id);
        return Response::empty();
    }
}
