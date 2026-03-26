<?php

declare(strict_types=1);

namespace kintai\UI\Controller\Api;

use kintai\Core\Exceptions\NotFoundException;
use kintai\Core\Repositories\UserRepositoryInterface;
use kintai\Core\Request;
use kintai\Core\Response;

final class UserController
{
    public function __construct(private readonly UserRepositoryInterface $users) {}

    /** GET /api/users */
    public function index(Request $request): Response
    {
        return Response::json($this->users->findAll());
    }

    /** GET /api/users/{id} */
    public function show(Request $request): Response
    {
        $user = $this->users->findById((int) $request->param('id'));
        if ($user === null) {
            throw new NotFoundException('Utilisateur introuvable.');
        }
        return Response::json($user);
    }

    /** POST /api/users */
    public function store(Request $request): Response
    {
        $data = $request->json() ?? [];
        $saved = $this->users->save($data);
        return Response::json($saved, 201);
    }

    /** PUT /api/users/{id} */
    public function update(Request $request): Response
    {
        $id = (int) $request->param('id');
        if ($this->users->findById($id) === null) {
            throw new NotFoundException('Utilisateur introuvable.');
        }
        $data = array_merge($request->json() ?? [], ['id' => $id]);
        return Response::json($this->users->save($data));
    }

    /** DELETE /api/users/{id} */
    public function destroy(Request $request): Response
    {
        $id = (int) $request->param('id');
        if ($this->users->findById($id) === null) {
            throw new NotFoundException('Utilisateur introuvable.');
        }
        $this->users->delete($id);
        return Response::empty();
    }
}
