<?php

declare(strict_types=1);

namespace kintai\Core;

final class Request
{
    private readonly string $method;
    private readonly string $uri;
    private readonly array $query;
    private readonly array $post;
    private readonly array $server;
    private readonly array $cookies;
    private readonly array $files;
    private ?array $jsonBody = null;

    /** @var array<string, string> Route parameters */
    private array $routeParams = [];

    /** @var array<string, mixed> Arbitrary attributes set by middleware */
    private array $attributes = [];

    public function __construct()
    {
        $this->server = $_SERVER;
        $this->query = $_GET;
        $this->post = $_POST;
        $this->cookies = $_COOKIE;
        $this->files = $_FILES;

        // Method override: _method field or X-HTTP-Method-Override header
        $rawMethod = strtoupper($this->server['REQUEST_METHOD'] ?? 'GET');
        if ($rawMethod === 'POST') {
            $override = $this->post['_method'] ?? $this->header('X-HTTP-Method-Override');
            if ($override !== null) {
                $rawMethod = strtoupper($override);
            }
        }
        $this->method = $rawMethod;

        // Parse URI (strip query string)
        $uri = $this->server['REQUEST_URI'] ?? '/';
        $pos = strpos($uri, '?');
        $uri = $pos !== false ? substr($uri, 0, $pos) : $uri;

        // Supprimer le préfixe du répertoire de script (ex: /MyShift/public)
        // pour que les routes soient définies sans ce préfixe.
        $scriptBase = rtrim(str_replace('\\', '/', dirname($this->server['SCRIPT_NAME'] ?? '/')), '/');
        if ($scriptBase !== '' && $scriptBase !== '.' && str_starts_with($uri, $scriptBase)) {
            $uri = substr($uri, strlen($scriptBase));
        }

        $this->uri = '/' . trim(rawurldecode($uri), '/');
    }

    public function method(): string
    {
        return $this->method;
    }

    public function uri(): string
    {
        return $this->uri;
    }

    public function query(string $key, mixed $default = null): mixed
    {
        return $this->query[$key] ?? $default;
    }

    public function allQuery(): array
    {
        return $this->query;
    }

    public function post(string $key, mixed $default = null): mixed
    {
        return $this->post[$key] ?? $default;
    }

    public function allPost(): array
    {
        return $this->post;
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->post[$key] ?? $this->query[$key] ?? $this->json($key) ?? $default;
    }

    public function json(?string $key = null): mixed
    {
        if ($this->jsonBody === null) {
            $raw = file_get_contents('php://input');
            $this->jsonBody = $raw ? (json_decode($raw, true) ?? []) : [];
        }

        if ($key === null) {
            return $this->jsonBody;
        }

        return $this->jsonBody[$key] ?? null;
    }

    public function header(string $name): ?string
    {
        // Convert header name to $_SERVER key format
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        return $this->server[$key] ?? null;
    }

    public function cookie(string $name, mixed $default = null): mixed
    {
        return $this->cookies[$name] ?? $default;
    }

    public function file(string $key): ?array
    {
        return $this->files[$key] ?? null;
    }

    public function server(string $key, mixed $default = null): mixed
    {
        return $this->server[$key] ?? $default;
    }

    public function ip(): string
    {
        return $this->server['REMOTE_ADDR'] ?? '127.0.0.1';
    }

    public function isAjax(): bool
    {
        return $this->header('X-Requested-With') === 'XMLHttpRequest'
            || str_contains($this->header('Accept') ?? '', 'application/json');
    }

    public function wantsJson(): bool
    {
        return str_starts_with($this->uri, '/api/')
            || $this->isAjax();
    }

    // --- Route params ---

    public function setRouteParams(array $params): void
    {
        $this->routeParams = $params;
    }

    public function param(string $key, mixed $default = null): mixed
    {
        return $this->routeParams[$key] ?? $default;
    }

    public function routeParams(): array
    {
        return $this->routeParams;
    }

    // --- Attributes (set by middleware) ---

    public function setAttribute(string $key, mixed $value): void
    {
        $this->attributes[$key] = $value;
    }

    public function getAttribute(string $key, mixed $default = null): mixed
    {
        return $this->attributes[$key] ?? $default;
    }
}
