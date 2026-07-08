<?php

declare(strict_types=1);

namespace kintai\UI\Controller\Api\V1;

use kintai\Core\Api\Paginator;
use kintai\Core\Exceptions\NotFoundException;
use kintai\Core\Repositories\ShiftClaimRepositoryInterface;
use kintai\Core\Request;
use kintai\Core\Response;

final class ShiftClaimController
{
    public function __construct(private readonly ShiftClaimRepositoryInterface $claims) {}

    /** GET /api/v1/shift-claims?store_id=X&user_id=Y&shift_id=Z&status=W&page=1&limit=20 */
    public function index(Request $request): Response
    {
        [$page, $limit] = Paginator::params($request);
        $storeId = $request->query('store_id');
        $userId  = $request->query('user_id');
        $shiftId = $request->query('shift_id');
        $status  = $request->query('status');

        if ($shiftId !== null) {
            $items = $this->claims->findByShift((int) $shiftId);
        } elseif ($userId !== null) {
            $items = $this->claims->findByUser((int) $userId);
        } elseif ($storeId !== null && $status === 'pending') {
            $items = $this->claims->findPendingByStore((int) $storeId);
        } elseif ($storeId !== null) {
            $items = $this->claims->findByStore((int) $storeId);
        } else {
            $items = $this->claims->findAll();
        }

        return Response::json(Paginator::paginate($items, $page, $limit));
    }

    /** GET /api/v1/shift-claims/{id} */
    public function show(Request $request): Response
    {
        $claim = $this->claims->findById((int) $request->param('id'));
        if ($claim === null) {
            throw new NotFoundException('Candidature introuvable.');
        }
        return Response::json($claim);
    }

    /** POST /api/v1/shift-claims */
    public function store(Request $request): Response
    {
        $data = array_merge($request->json() ?? [], ['claimed_at' => date('Y-m-d H:i:s')]);
        return Response::json($this->claims->save($data), 201);
    }

    /** PUT /api/v1/shift-claims/{id} */
    public function update(Request $request): Response
    {
        $id = (int) $request->param('id');
        if ($this->claims->findById($id) === null) {
            throw new NotFoundException('Candidature introuvable.');
        }
        return Response::json($this->claims->save(array_merge($request->json() ?? [], ['id' => $id])));
    }

    /** DELETE /api/v1/shift-claims/{id} */
    public function destroy(Request $request): Response
    {
        $id = (int) $request->param('id');
        if ($this->claims->findById($id) === null) {
            throw new NotFoundException('Candidature introuvable.');
        }
        $this->claims->delete($id);
        return Response::empty();
    }
}
