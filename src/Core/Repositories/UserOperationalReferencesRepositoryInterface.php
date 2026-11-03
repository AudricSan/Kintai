<?php

declare(strict_types=1);

namespace kintai\Core\Repositories;

interface UserOperationalReferencesRepositoryInterface
{
    public function transaction(callable $callback): mixed;

    /** Supprime les associations opérationnelles d'un utilisateur sans toucher à son historique. */
    public function deleteForUser(int $userId): void;
}
