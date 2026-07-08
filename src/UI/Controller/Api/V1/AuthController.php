<?php

declare(strict_types=1);

namespace kintai\UI\Controller\Api\V1;

use kintai\Core\Auth\AuthService;
use kintai\Core\Exceptions\NotFoundException;
use kintai\Core\Repositories\ApiTokenRepositoryInterface;
use kintai\Core\Repositories\UserRepositoryInterface;
use kintai\Core\Request;
use kintai\Core\Response;

final class AuthController
{
    public function __construct(
        private readonly AuthService $auth,
        private readonly ApiTokenRepositoryInterface $tokens,
        private readonly UserRepositoryInterface $users,
    ) {}

    /**
     * POST /api/v1/auth/login
     * Body : { "email": "...", "password": "..." }
     *     OU { "employee_code": "...", "store_code": "...", "password": "..." }
     */
    public function login(Request $request): Response
    {
        $data     = $request->json() ?? [];
        $password = (string) ($data['password'] ?? '');
        $name     = (string) ($data['token_name'] ?? 'API');

        if (isset($data['email'])) {
            $ok = $this->auth->attempt((string) $data['email'], $password);
        } elseif (isset($data['employee_code'], $data['store_code'])) {
            $ok = $this->auth->attemptByCode(
                (string) $data['employee_code'],
                (string) $data['store_code'],
                $password,
            );
        } else {
            return Response::apiError(
                'Identifiants manquants. Fournir email+password ou employee_code+store_code+password.',
                422, 'MISSING_CREDENTIALS'
            );
        }

        if (!$ok) {
            return Response::apiError('Identifiants invalides.', 401, 'INVALID_CREDENTIALS');
        }

        $user      = $this->auth->user();
        $rawToken  = bin2hex(random_bytes(32));
        $expiresAt = isset($data['expires_in'])
            ? date('Y-m-d H:i:s', time() + (int) $data['expires_in'])
            : null;

        $record = $this->tokens->save([
            'user_id'    => (int) $user['id'],
            'token'      => hash_token($rawToken),
            'name'       => $name,
            'expires_at' => $expiresAt,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        // Détruire la session PHP créée par AuthService::attempt (l'API est stateless)
        $this->auth->logout();

        return Response::json([
            'token'      => $rawToken,
            'token_id'   => $record['id'],
            'expires_at' => $expiresAt,
            'user'       => $this->sanitizeUser($user),
        ], 201);
    }

    /** POST /api/v1/auth/logout — révoque le token courant */
    public function logout(Request $request): Response
    {
        $tokenRecord = $request->getAttribute('api_token');
        if ($tokenRecord !== null) {
            $this->tokens->delete((int) $tokenRecord['id']);
        }

        return Response::empty();
    }

    /** GET /api/v1/auth/me — utilisateur courant */
    public function me(Request $request): Response
    {
        $user = $request->getAttribute('auth_user');
        return Response::json($this->sanitizeUser($user));
    }

    /** GET /api/v1/auth/tokens — liste les tokens du user courant */
    public function listTokens(Request $request): Response
    {
        $user   = $request->getAttribute('auth_user');
        $tokens = $this->tokens->findByUserId((int) $user['id']);

        return Response::json(array_map(fn($t) => $this->sanitizeToken($t), $tokens));
    }

    /** DELETE /api/v1/auth/tokens/{id} — révoque un token spécifique */
    public function revokeToken(Request $request): Response
    {
        $id          = (int) $request->param('id');
        $user        = $request->getAttribute('auth_user');
        $tokenRecord = $this->tokens->findById($id);

        if ($tokenRecord === null || (int) $tokenRecord['user_id'] !== (int) $user['id']) {
            throw new NotFoundException('Token introuvable.');
        }

        $this->tokens->delete($id);
        return Response::empty();
    }

    /** GET /api/v1/ping */
    public function ping(Request $request): Response
    {
        return Response::json(['status' => 'ok', 'version' => 'v1']);
    }

    private function sanitizeUser(array $user): array
    {
        unset($user['password_hash']);
        return $user;
    }

    private function sanitizeToken(array $token): array
    {
        unset($token['token']);
        return $token;
    }
}
