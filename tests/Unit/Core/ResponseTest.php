<?php

declare(strict_types=1);

namespace kintai\Tests\Unit\Core;

use PHPUnit\Framework\TestCase;
use kintai\Core\Response;

final class ResponseTest extends TestCase
{
    public function testHtmlResponse(): void
    {
        $r = Response::html('<h1>Hi</h1>', 200);

        $this->assertSame(200, $r->status());
        $this->assertSame('<h1>Hi</h1>', $r->body());
    }

    public function testJsonResponse(): void
    {
        $r = Response::json(['ok' => true], 201);

        $this->assertSame(201, $r->status());
        $this->assertSame('{"ok":true}', $r->body());
    }

    public function testRedirectResponse(): void
    {
        $r = Response::redirect('/login', 302);

        $this->assertSame(302, $r->status());
    }

    public function testEmptyResponse(): void
    {
        $r = Response::empty();

        $this->assertSame(204, $r->status());
        $this->assertSame('', $r->body());
    }

    public function testWithStatus(): void
    {
        $r = Response::html('ok')->withStatus(201);

        $this->assertSame(201, $r->status());
    }
}
