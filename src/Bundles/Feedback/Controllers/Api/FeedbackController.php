<?php

declare(strict_types=1);

namespace kintai\Bundles\Feedback\Controllers\Api;

use kintai\Core\Api\Paginator;
use kintai\Core\Auth\PermissionService;
use kintai\Core\Exceptions\ForbiddenException;
use kintai\Core\Exceptions\NotFoundException;
use kintai\Core\Repositories\FeedbackRepositoryInterface;
use kintai\Core\Request;
use kintai\Core\Response;

/**
 * Régression (audit RBAC du 11/09/2026) : mêmes correctifs que
 * ShiftSwapRequestController — voir son docblock pour le détail de la faille.
 * index() sans filtre renvoyait en plus TOUS les feedbacks de TOUS les stores
 * à n'importe quel porteur de token (findAll() sans restriction).
 */
final class FeedbackController
{
    public function __construct(
        private readonly FeedbackRepositoryInterface $feedbacks,
        private readonly PermissionService $permissions,
    ) {}

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

        $items = $this->permissions->restrictToScope($this->authUser($request), 'feedbacks.view', $items);

        return Response::json(Paginator::paginate($items, $page, $limit));
    }

    /** GET /api/v1/feedbacks/{id} */
    public function show(Request $request): Response
    {
        $item = $this->requireFeedback($request, 'feedbacks.view');
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
        $item = $this->requireFeedback($request, 'feedbacks.update');
        $id   = (int) $item['id'];
        return Response::json($this->feedbacks->save(array_merge($request->json() ?? [], ['id' => $id])));
    }

    /** DELETE /api/v1/feedbacks/{id} */
    public function destroy(Request $request): Response
    {
        $item = $this->requireFeedback($request, 'feedbacks.delete');
        $this->feedbacks->delete((int) $item['id']);
        return Response::empty();
    }

    private function authUser(Request $request): array
    {
        return $request->getAttribute('auth_user') ?? [];
    }

    /** Charge le feedback par id et vérifie $permissionKey sur son store réel. */
    private function requireFeedback(Request $request, string $permissionKey): array
    {
        $item = $this->feedbacks->findById((int) $request->param('id'));
        if ($item === null) {
            throw new NotFoundException('Feedback introuvable.');
        }
        if (!$this->permissions->can($this->authUser($request), $permissionKey, (int) ($item['store_id'] ?? 0))) {
            throw new ForbiddenException('Permission insuffisante : ' . $permissionKey);
        }
        return $item;
    }
}
