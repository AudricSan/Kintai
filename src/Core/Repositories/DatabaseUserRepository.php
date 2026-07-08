<?php

declare(strict_types=1);


namespace kintai\Core\Repositories;

use kintai\Domain\Eloquent\User as EloquentUser;

final class DatabaseUserRepository implements UserRepositoryInterface
{
    public function __construct() {}

    /**
     * Finds a user by their ID.
     * @param int $id
     * @return array|null
     */
    public function findById(int $id): ?array
    {
        $user = EloquentUser::find($id);
        return $user ? $user->toArray() : null;
    }

    /**
     * Finds a user by their email address.
     * @param string $email
     * @return array|null
     */
    public function findByEmail(string $email): ?array
    {
        $user = EloquentUser::where('email', $email)->first();
        return $user ? $user->toArray() : null;
    }

    public function findByEmployeeCode(string $code): ?array
    {
        $user = EloquentUser::where('employee_code', $code)->first();
        return $user ? $user->toArray() : null;
    }

    /**
     * Retrieves all users.
     * @return array
     */
    public function findAll(): array
    {
        return EloquentUser::all()->toArray();
    }

    /**
     * Saves a user record. Creates if no ID, updates if ID exists.
     * @param array $userData
     * @return array The saved user data.
     */
    public function save(array $userData): array
    {
        if (!empty($userData['id'])) {
            $user = EloquentUser::findOrFail($userData['id']);
            $user->fill($userData);
            $user->save();
        } else {
            $user = EloquentUser::create($userData);
        }
        
        return $user->toArray();
    }

    /**
     * Deletes a user by their ID.
     * @param int $id
     * @return int The number of deleted users (0 or 1).
     */
    public function delete(int $id): int
    {
        $user = EloquentUser::find($id);
        if ($user) {
            return $user->delete() ? 1 : 0;
        }
        return 0;
    }
}
