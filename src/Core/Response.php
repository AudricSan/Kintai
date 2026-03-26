<?php

declare(strict_types=1);

namespace kintai\Core;

final class Response
{
    private string $body = '';
    private int $status = 200;

    /** @var array<string, string> */
    private array $headers = [];

    public static function html(string $body, int $status = 200): self
    {
        $r = new self();
        $r->body = $body;
        $r->status = $status;
        $r->headers['Content-Type'] = 'text/html; charset=UTF-8';
        return $r;
    }

    public static function json(mixed $data, int $status = 200): self
    {
        $r = new self();
        $r->body = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $r->status = $status;
        $r->headers['Content-Type'] = 'application/json; charset=UTF-8';
        return $r;
    }

    public static function redirect(string $url, int $status = 302): self
    {
        $r = new self();
        $r->status = $status;
        $r->headers['Location'] = $url;
        return $r;
    }

    public static function csv(string $body, string $filename): self
    {
        $r = new self();
        $r->body = $body;
        $r->status = 200;
        $r->headers['Content-Type'] = 'text/csv; charset=UTF-8';
        $r->headers['Content-Disposition'] = "attachment; filename=\"{$filename}\"";
        return $r;
    }

    public static function pdf(string $body, string $filename): self
    {
        $r = new self();
        $r->body = $body;
        $r->status = 200;
        $r->headers['Content-Type'] = 'application/pdf';
        $r->headers['Content-Disposition'] = "attachment; filename=\"{$filename}\"";
        $r->headers['Content-Length'] = (string) strlen($body);
        return $r;
    }

    public static function empty(int $status = 204): self
    {
        $r = new self();
        $r->status = $status;
        return $r;
    }

    public function withHeader(string $name, string $value): self
    {
        $this->headers[$name] = $value;
        return $this;
    }

    public function withStatus(int $status): self
    {
        $this->status = $status;
        return $this;
    }

    public function status(): int
    {
        return $this->status;
    }

    public function body(): string
    {
        return $this->body;
    }

    public function send(): void
    {
        http_response_code($this->status);

        foreach ($this->headers as $name => $value) {
            header("{$name}: {$value}");
        }

        echo $this->body;
    }
}
