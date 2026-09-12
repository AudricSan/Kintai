<?php

declare(strict_types=1);

namespace kintai\Core\Repositories;

interface DevicePushTokenRepositoryInterface
{
    public function findByUser(int $userId): array;

    public function findByToken(string $token): ?array;

    /** Upsert par token (un jeton FCM peut migrer d'un utilisateur à l'autre sur un appareil partagé). */
    public function save(array $data): array;

    public function deleteByToken(string $token): int;

    public function touchLastUsed(int $id): void;
}
