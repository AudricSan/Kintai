<?php

declare(strict_types=1);

namespace kintai\UI\Controller\Api\V1;

use kintai\Core\Api\Paginator;
use kintai\Core\Exceptions\NotFoundException;
use kintai\Core\Repositories\FeedbackRepositoryInterface;
use kintai\Core\Request;
use kintai\Core\Response;

final class FeedbackController
{
    public function __construct(private readonly FeedbackRepositoryInterface $feedbacks) {}

    /** GET /api/v1/feedbacks?store_id=X&shift_id=Y&page=1&limit=20 */
    public function index(Request $request): Response
    {
        [$page, $limit] = Paginator::params($request);
        $storeId = $request->query('store_id');
        $shiftId = $request->query('shift_id');

        if ($shiftId !== null) {
            $item  = $this->feedbacks->findByShift((int) $shiftId);
            $items = $item !== null ? [$item] : [];
        } elseif ($storeId !== null) {
            $items = $this->feedbacks->findByStore((int) $storeId);
        } else {
            $items = $this->feedbacks->findAll();
        }

        return Response::json(Paginator::paginate($items, $page, $limit));
    }

    /** GET /api/v1/feedbacks/{id} */
    public function show(Request $request): Response
    {
        $item = $this->feedbacks->findById((int) $request->param('id'));
        if ($item === null) {
            throw new NotFoundException('Feedback introuvable.');
        }
        return Response::json($item);
    }

    /** POST /api/v1/feedbacks */
    public function store(Request $request): Response
    {
        $data = array_merge($request->json() ?? [], ['created_at' => date('Y-m-d H:i:s')]);
        return Response::json($this->feedbacks->save($data), 201);
    }

    /** PUT /api/v1/feedbacks/{id} */
    public function update(Request $request): Response
    {
        $id = (int) $request->param('id');
        if ($this->feedbacks->findById($id) === null) {
            throw new NotFoundException('Feedback introuvable.');
        }
        return Response::json($this->feedbacks->save(array_merge($request->json() ?? [], ['id' => $id])));
    }

    /** DELETE /api/v1/feedbacks/{id} */
    public function destroy(Request $request): Response
    {
        $id = (int) $request->param('id');
        if ($this->feedbacks->findById($id) === null) {
            throw new NotFoundException('Feedback introuvable.');
        }
        $this->feedbacks->delete($id);
        return Response::empty();
    }
}
