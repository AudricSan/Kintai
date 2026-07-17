<?php

declare(strict_types=1);

namespace kintai\Tests\Unit\Controller\Api\V1;

use kintai\Core\Auth\AuthService;
use kintai\Core\Exceptions\NotFoundException;
use kintai\Core\Repositories\ApiTokenRepositoryInterface;
use kintai\Core\Repositories\RoleAssignmentRepositoryInterface;
use kintai\Core\Repositories\RoleRepositoryInterface;
use kintai\Core\Repositories\StoreRepositoryInterface;
use kintai\Core\Repositories\StoreUserRepositoryInterface;
use kintai\Core\Repositories\UserRepositoryInterface;
use kintai\Core\Request;
use kintai\UI\Controller\Api\V1\AuthController;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class AuthControllerTest extends TestCase
{
    private UserRepositoryInterface&MockObject     $users;
    private ApiTokenRepositoryInterface&MockObject $tokens;
    private AuthService $auth;
    private AuthController $controller;

    protected function setUp(): void
    {
        $storeUsers = $this->createMock(StoreUserRepositoryInterface::class);
        $stores     = $this->createMock(StoreRepositoryInterface::class);
        $roles           = $this->createMock(RoleRepositoryInterface::class);
        $roleAssignments = $this->createMock(RoleAssignmentRepositoryInterface::class);
        $this->users  = $this->createMock(UserRepositoryInterface::class);
        $this->tokens = $this->createMock(ApiTokenRepositoryInterface::class);

        $this->auth       = new AuthService($this->users, $storeUsers, $stores, $roles, $roleAssignments);
        $this->controller = new AuthController($this->auth, $this->tokens, $this->users);
    }

    // -------------------------------------------------------------------------
    // ping
    // -------------------------------------------------------------------------

    public function testPingReturnsOk(): void
    {
        $req      = $this->makeRequest('GET', '/api/v1/ping');
        $response = $this->controller->ping($req);

        $this->assertSame(200, $response->status());
        $data = json_decode($response->body(), true);
        $this->assertSame('ok', $data['status']);
        $this->assertSame('v1', $data['version']);
    }

    // -------------------------------------------------------------------------
    // login — validation
    // -------------------------------------------------------------------------

    public function testLoginMissingCredentialsReturns422(): void
    {
        $req      = $this->makeRequest('POST', '/api/v1/auth/login', json: ['password' => 'x']);
        $response = $this->controller->login($req);

        $this->assertSame(422, $response->status());
        $data = json_decode($response->body(), true);
        $this->assertSame('MISSING_CREDENTIALS', $data['code']);
    }

    public function testLoginInvalidEmailReturns401(): void
    {
        $this->users->method('findByEmail')->willReturn(null);

        $req      = $this->makeRequest('POST', '/api/v1/auth/login', json: [
            'email'    => 'nobody@example.com',
            'password' => 'wrong',
        ]);
        $response = $this->controller->login($req);

        $this->assertSame(401, $response->status());
        $data = json_decode($response->body(), true);
        $this->assertSame('INVALID_CREDENTIALS', $data['code']);
    }

    public function testLoginInvalidEmployeeCodeReturns401(): void
    {
        $this->users->method('findByEmployeeCode')->willReturn(null);

        $req      = $this->makeRequest('POST', '/api/v1/auth/login', json: [
            'employee_code' => 'EMP001',
            'store_code'    => 'SHOP1',
            'password'      => 'wrong',
        ]);
        $response = $this->controller->login($req);

        $this->assertSame(401, $response->status());
    }

    public function testLoginSuccessReturnsToken(): void
    {
        $hash = password_hash('secret', PASSWORD_BCRYPT);
        $this->users->method('findByEmail')->willReturn([
            'id'            => 7,
            'email'         => 'alice@example.com',
            'password_hash' => $hash,
            'is_active'     => true,
            'deleted_at'    => null,
        ]);
        $this->users->method('findById')->willReturn([
            'id'         => 7,
            'email'      => 'alice@example.com',
            'is_active'  => true,
            'deleted_at' => null,
        ]);
        $this->tokens->method('save')->willReturn([
            'id'         => 1,
            'user_id'    => 7,
            'token'      => 'rawtoken',
            'expires_at' => null,
        ]);

        $req      = $this->makeRequest('POST', '/api/v1/auth/login', json: [
            'email'    => 'alice@example.com',
            'password' => 'secret',
        ]);
        $response = $this->controller->login($req);

        $this->assertSame(201, $response->status());
        $data = json_decode($response->body(), true);
        $this->assertArrayHasKey('token', $data);
        $this->assertArrayHasKey('user', $data);
        $this->assertArrayNotHasKey('password_hash', $data['user']);
    }

    public function testLoginTokenLengthIs64Hex(): void
    {
        $hash = password_hash('pass', PASSWORD_BCRYPT);
        $this->users->method('findByEmail')->willReturn([
            'id'            => 1,
            'email'         => 'x@x.com',
            'password_hash' => $hash,
            'is_active'     => true,
            'deleted_at'    => null,
        ]);
        $this->users->method('findById')->willReturn([
            'id' => 1, 'email' => 'x@x.com', 'is_active' => true, 'deleted_at' => null,
        ]);

        $capturedToken = null;
        $this->tokens->method('save')->willReturnCallback(function ($data) use (&$capturedToken) {
            $capturedToken = $data['token'];
            return array_merge($data, ['id' => 1]);
        });

        $req = $this->makeRequest('POST', '/api/v1/auth/login', json: [
            'email' => 'x@x.com', 'password' => 'pass',
        ]);
        $this->controller->login($req);

        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $capturedToken);
    }

    // -------------------------------------------------------------------------
    // logout
    // -------------------------------------------------------------------------

    public function testLogoutDeletesToken(): void
    {
        $this->tokens->expects($this->once())->method('delete')->with(42);

        $req = $this->makeRequest('POST', '/api/v1/auth/logout');
        $req->setAttribute('api_token', ['id' => 42]);

        $response = $this->controller->logout($req);
        $this->assertSame(204, $response->status());
    }

    public function testLogoutWithoutTokenAttributeIsGraceful(): void
    {
        $this->tokens->expects($this->never())->method('delete');

        $req      = $this->makeRequest('POST', '/api/v1/auth/logout');
        $response = $this->controller->logout($req);

        $this->assertSame(204, $response->status());
    }

    // -------------------------------------------------------------------------
    // me
    // -------------------------------------------------------------------------

    public function testMeReturnsUserWithoutPasswordHash(): void
    {
        $user = ['id' => 1, 'email' => 'a@b.com', 'password_hash' => 'secret'];

        $req = $this->makeRequest('GET', '/api/v1/auth/me');
        $req->setAttribute('auth_user', $user);

        $response = $this->controller->me($req);

        $this->assertSame(200, $response->status());
        $data = json_decode($response->body(), true);
        $this->assertArrayNotHasKey('password_hash', $data);
        $this->assertSame('a@b.com', $data['email']);
    }

    // -------------------------------------------------------------------------
    // listTokens
    // -------------------------------------------------------------------------

    public function testListTokensHidesRawToken(): void
    {
        $this->tokens->method('findByUserId')->willReturn([
            ['id' => 1, 'user_id' => 3, 'token' => 'secret1', 'name' => 'CI'],
            ['id' => 2, 'user_id' => 3, 'token' => 'secret2', 'name' => 'App'],
        ]);

        $req = $this->makeRequest('GET', '/api/v1/auth/tokens');
        $req->setAttribute('auth_user', ['id' => 3]);

        $response = $this->controller->listTokens($req);

        $this->assertSame(200, $response->status());
        $data = json_decode($response->body(), true);
        $this->assertCount(2, $data);
        foreach ($data as $row) {
            $this->assertArrayNotHasKey('token', $row);
        }
    }

    // -------------------------------------------------------------------------
    // revokeToken
    // -------------------------------------------------------------------------

    public function testRevokeTokenDeletesWhenOwnerMatches(): void
    {
        $this->tokens->method('findById')->willReturn(['id' => 5, 'user_id' => 3]);
        $this->tokens->expects($this->once())->method('delete')->with(5);

        $req = $this->makeRequest('DELETE', '/api/v1/auth/tokens/5');
        $req->setRouteParams(['id' => '5']);
        $req->setAttribute('auth_user', ['id' => 3]);

        $response = $this->controller->revokeToken($req);
        $this->assertSame(204, $response->status());
    }

    public function testRevokeTokenThrowsWhenOwnerMismatch(): void
    {
        $this->tokens->method('findById')->willReturn(['id' => 5, 'user_id' => 99]);

        $req = $this->makeRequest('DELETE', '/api/v1/auth/tokens/5');
        $req->setRouteParams(['id' => '5']);
        $req->setAttribute('auth_user', ['id' => 3]);

        $this->expectException(NotFoundException::class);
        $this->controller->revokeToken($req);
    }

    public function testRevokeTokenThrowsWhenTokenNotFound(): void
    {
        $this->tokens->method('findById')->willReturn(null);

        $req = $this->makeRequest('DELETE', '/api/v1/auth/tokens/999');
        $req->setRouteParams(['id' => '999']);
        $req->setAttribute('auth_user', ['id' => 3]);

        $this->expectException(NotFoundException::class);
        $this->controller->revokeToken($req);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makeRequest(string $method, string $uri, array $json = []): Request
    {
        $_SERVER = [
            'REQUEST_METHOD' => $method,
            'REQUEST_URI'    => $uri,
            'SCRIPT_NAME'    => '/index.php',
        ];
        $_GET    = [];
        $_POST   = [];
        $_COOKIE = [];
        $_FILES  = [];

        if ($json !== []) {
            // On simule un corps JSON via php://input n'est pas possible dans un test unitaire.
            // On réutilise la réflexion pour injecter le corps JSON directement.
            $req = new Request();
            $ref = new \ReflectionProperty(Request::class, 'jsonBody');
            $ref->setAccessible(true);
            $ref->setValue($req, $json);
            return $req;
        }

        return new Request();
    }

    protected function tearDown(): void
    {
        $_SERVER = [];
        $_GET    = [];
        $_POST   = [];
    }
}
