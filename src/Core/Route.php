<?php

declare(strict_types=1);

namespace kintai\Core;

final readonly class Route
{
    /**
     * @param string $method HTTP method
     * @param string $pattern Original pattern (e.g. /stores/{storeId}/shifts)
     * @param string $regex Compiled regex
     * @param array $handler [ControllerClass, method]
     * @param string[] $paramNames Ordered parameter names
     * @param string[] $middleware Middleware class names
     * @param string|null $name Route name for URL generation
     */
    public function __construct(
        public string $method,
        public string $pattern,
        public string $regex,
        public array $handler,
        public array $paramNames,
        public array $middleware = [],
        public ?string $name = null,
    ) {}
}
