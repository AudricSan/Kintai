<?php

declare(strict_types=1);

namespace kintai\Bundles\Installed\Feedback\Controllers\Api;

use kintai\Core\Api\Paginator;
use kintai\Core\Auth\PermissionService;
use kintai\Core\Exceptions\ForbiddenException;
use kintai\Core\Exceptions\ValidationException;
use kintai\Core\Repositories\FeedbackRepositoryInterface;
use kintai\Core\Repositories\StoreUserRepositoryInterface;
use kintai\Core\Request;
use kintai\Core\Response;

/**
 * Régression (audit RBAC du 11/09/2026) : mêmes correctifs que
 * ShiftSwapRequestController — voir son docblock pour le détail de la faille.
 * index() sans filtre renvoyait en plus TOUS les feedbacks de TOUS les stores
 * à n'importe quel porteur de token (findAll() sans restriction).
 *
 * Régression (audit du 03/10/2026) : store() et update() fusionnaient le JSON brut du client.
 * Un `id` dans le corps d'un POST écrasait le feedback de quelqu'un d'autre (save() fait un upsert
 * sur l'id, ce qui contournait requireFeedback()), et `user_id`/`store_id` étaient pris tels quels.
 * Seuls les champs de la liste blanche sont maintenant acceptés.
 */
final class FeedbackController
{
    /** Champs qu'un client peut renseigner à la création (user_id, store_id et created_at sont imposés). */
    private const CREATE_FIELDS = ['shift_id', 'category', 'rating', 'message', 'anonymous', 'page_path', 'app_version', 'device_type'];

    /** Champs modifiables ensuite. */
    private const UPDATE_FIELDS = ['category', 'rating', 'message', 'anonymous'];

    public function __construct(
        private readonly FeedbackRepositoryInterface $feedbacks,
        private readonly PermissionService $permissions,
        private readonly StoreUserRepositoryInterface $storeUsers,
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
        $authUser = $this->authUser($request);
        $userId   = (int) ($authUser['id'] ?? 0);
        $body     = $request->json() ?? [];

        $storeId = (int) ($body['store_id'] ?? 0);
        if ($storeId <= 0) {
            throw new ValidationException(['store_id' => __('error_api_store_id_required')]);
        }
        // Un feedback se dépose depuis son propre magasin (ou par un gestionnaire de ce magasin).
        if ($this->storeUsers->findMembership($storeId, $userId) === null
            && !$this->permissions->can($authUser, 'feedbacks.update', $storeId)) {
            throw new ForbiddenException(__('error_permission_insufficient', ['key' => 'feedbacks.update']));
        }

        $data = array_intersect_key($body, array_flip(self::CREATE_FIELDS)) + [
            'user_id'    => $userId,
            'store_id'   => $storeId,
            'created_at' => date('Y-m-d H:i:s'),
        ];
        return Response::json($this->feedbacks->save($data), 201);
    }

    /** PUT /api/v1/feedbacks/{id} */
    public function update(Request $request): Response
    {
        $item = $this->requireFeedback($request, 'feedbacks.update');
        $id   = (int) $item['id'];
        // Ni l'auteur, ni le magasin, ni le shift ne changent : seul le contenu est modifiable.
        $data = array_intersect_key($request->json() ?? [], array_flip(self::UPDATE_FIELDS)) + ['id' => $id];
        return Response::json($this->feedbacks->save($data));
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
        return $this->permissions->requireOwnedResource(
            $this->authUser($request),
            fn(int $id) => $this->feedbacks->findById($id),
            (int) $request->param('id'),
            $permissionKey,
            notFoundMessage: 'Feedback introuvable.',
        );
    }
}
