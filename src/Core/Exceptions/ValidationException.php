<?php

declare(strict_types=1);

namespace kintai\Core\Exceptions;

final class ValidationException extends HttpException
{
    public function __construct(
        public readonly array $errors,
        string $message = 'Validation failed',
    ) {
        parent::__construct(422, $message);
    }
}
