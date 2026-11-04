<?php

declare(strict_types=1);

namespace kintai\Tests\Support;

use kintai\Core\Repositories\UserOperationalReferencesRepositoryInterface;

final class InlineUserOperationalReferencesRepository implements UserOperationalReferencesRepositoryInterface
{
    public function transaction(callable $callback): mixed
    {
        return $callback();
    }

    public function deleteForUser(int $userId): void {}
}
