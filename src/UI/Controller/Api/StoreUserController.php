<?php

declare(strict_types=1);

namespace kintai\UI\Controller\Api;

use kintai\Core\Exceptions\NotFoundException;
use kintai\Core\Repositories\StoreUserRepositoryInterface;
use kintai\Core\Request;
use kintai\Core\Response;

final class StoreUserController
{
    public function __construct(private readonly StoreUserRepositoryInterface $storeUsers) {}

    /** GET /api/stores/{store_id}/members */
    public function index(Request $request): Response
    {
        $storeId = (int) $request->param('store_id');
        return Response::json($this->storeUsers->findByStore($storeId));
    }

    /** POST /api/stores/{store_id}/members */
    public function store(Request $request): Response
    {
        $storeId = (int) $request->param('store_id');
        $data = array_merge($request->json() ?? [], ['store_id' => $storeId]);
        $saved = $this->storeUsers->save($data);
        return Response::json($saved, 201);
    }

    /** DELETE /api/stores/{store_id}/members/{id} */
    public function destroy(Request $request): Response
    {
        $id = (int) $request->param('id');
        if ($this->storeUsers->findById($id) === null) {
            throw new NotFoundException('Appartenance introuvable.');
        }
        $this->storeUsers->delete($id);
        return Response::empty();
    }
}
