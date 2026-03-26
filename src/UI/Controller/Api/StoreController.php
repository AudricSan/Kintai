<?php

declare(strict_types=1);

namespace kintai\UI\Controller\Api;

use kintai\Core\Exceptions\NotFoundException;
use kintai\Core\Repositories\StoreRepositoryInterface;
use kintai\Core\Request;
use kintai\Core\Response;

final class StoreController
{
    public function __construct(private readonly StoreRepositoryInterface $stores) {}

    /** GET /api/stores */
    public function index(Request $request): Response
    {
        return Response::json($this->stores->findAll());
    }

    /** GET /api/stores/{id} */
    public function show(Request $request): Response
    {
        $store = $this->stores->findById((int) $request->param('id'));
        if ($store === null) {
            throw new NotFoundException('Store introuvable.');
        }
        return Response::json($store);
    }

    /** POST /api/stores */
    public function store(Request $request): Response
    {
        $data = $request->json() ?? [];
        $saved = $this->stores->save($data);
        return Response::json($saved, 201);
    }

    /** PUT /api/stores/{id} */
    public function update(Request $request): Response
    {
        $id = (int) $request->param('id');
        if ($this->stores->findById($id) === null) {
            throw new NotFoundException('Store introuvable.');
        }
        $data = array_merge($request->json() ?? [], ['id' => $id]);
        return Response::json($this->stores->save($data));
    }

    /** DELETE /api/stores/{id} */
    public function destroy(Request $request): Response
    {
        $id = (int) $request->param('id');
        if ($this->stores->findById($id) === null) {
            throw new NotFoundException('Store introuvable.');
        }
        $this->stores->delete($id);
        return Response::empty();
    }
}
