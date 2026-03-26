<?php

declare(strict_types=1);

namespace kintai\UI\Controller\Api;

use kintai\Core\Exceptions\NotFoundException;
use kintai\Core\Repositories\ShiftTypeRepositoryInterface;
use kintai\Core\Request;
use kintai\Core\Response;

final class ShiftTypeController
{
    public function __construct(private readonly ShiftTypeRepositoryInterface $shiftTypes) {}

    /** GET /api/shift-types */
    public function index(Request $request): Response
    {
        $storeId = $request->query('store_id');
        $data = $storeId !== null
            ? $this->shiftTypes->findByStore((int) $storeId)
            : $this->shiftTypes->findByStore(0); // retourne vide si pas de store_id
        return Response::json($data);
    }

    /** GET /api/shift-types/{id} */
    public function show(Request $request): Response
    {
        $type = $this->shiftTypes->findById((int) $request->param('id'));
        if ($type === null) {
            throw new NotFoundException('Type de shift introuvable.');
        }
        return Response::json($type);
    }

    /** POST /api/shift-types */
    public function store(Request $request): Response
    {
        $data = $request->json() ?? [];
        $saved = $this->shiftTypes->save($data);
        return Response::json($saved, 201);
    }

    /** PUT /api/shift-types/{id} */
    public function update(Request $request): Response
    {
        $id = (int) $request->param('id');
        if ($this->shiftTypes->findById($id) === null) {
            throw new NotFoundException('Type de shift introuvable.');
        }
        $data = array_merge($request->json() ?? [], ['id' => $id]);
        return Response::json($this->shiftTypes->save($data));
    }

    /** DELETE /api/shift-types/{id} */
    public function destroy(Request $request): Response
    {
        $id = (int) $request->param('id');
        if ($this->shiftTypes->findById($id) === null) {
            throw new NotFoundException('Type de shift introuvable.');
        }
        $this->shiftTypes->delete($id);
        return Response::empty();
    }
}
