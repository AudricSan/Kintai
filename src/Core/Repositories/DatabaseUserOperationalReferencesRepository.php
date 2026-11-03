<?php

declare(strict_types=1);

namespace kintai\Core\Repositories;

use kintai\Domain\Eloquent\ImportAlias;
use kintai\Domain\Eloquent\RouteSlug;
use kintai\Domain\Eloquent\StoreUser;
use kintai\Domain\Eloquent\User;

final class DatabaseUserOperationalReferencesRepository implements UserOperationalReferencesRepositoryInterface
{
    public function transaction(callable $callback): mixed
    {
        return User::query()->getConnection()->transaction($callback);
    }

    public function deleteForUser(int $userId): void
    {
        StoreUser::where('user_id', $userId)->delete();
        ImportAlias::where('user_id', $userId)->delete();
        RouteSlug::where('entity_type', RouteSlugRepositoryInterface::TYPE_EMPLOYEE)
            ->where('entity_id', $userId)
            ->delete();
    }
}
