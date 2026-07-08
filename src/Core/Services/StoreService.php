<?php

declare(strict_types=1);

namespace kintai\Core\Services;

use kintai\Core\Exceptions\NotFoundException;
use kintai\Core\Repositories\LanguageRepositoryInterface;
use kintai\Core\Repositories\StoreRepositoryInterface;
use kintai\Core\Repositories\StoreUserRepositoryInterface;
use kintai\Core\Repositories\UserRepositoryInterface;
use kintai\Core\Validation\StoreValidator;

final class StoreService implements StoreServiceInterface
{
    private const ROLES = ['admin' => 'Administrateur', 'manager' => 'Manager', 'staff' => 'Employé'];

    public function __construct(
        private readonly StoreRepositoryInterface $stores,
        private readonly StoreUserRepositoryInterface $storeUsers,
        private readonly UserRepositoryInterface $users,
        private readonly LanguageRepositoryInterface $languages,
    ) {}

    public function getStoresForAdmin(?array $managedIds, string $sort = 'name_asc'): array
    {
        $all = $this->stores->findAll();
        if ($managedIds !== null) {
            $all = array_values(array_filter($all, fn($s) => in_array((int) $s['id'], $managedIds, true)));
        }
        usort($all, function ($a, $b) use ($sort) {
            $codeA  = strtolower($a['code'] ?? '');
            $codeB  = strtolower($b['code'] ?? '');
            $nameA  = strtolower($a['name'] ?? '');
            $nameB  = strtolower($b['name'] ?? '');
            $typeA  = strtolower($a['type'] ?? '');
            $typeB  = strtolower($b['type'] ?? '');
            $statA  = (int) (!empty($a['is_active']) && empty($a['deleted_at']));
            $statB  = (int) (!empty($b['is_active']) && empty($b['deleted_at']));
            return match ($sort) {
                'name_desc'   => strcmp($nameB, $nameA),
                'code_asc'    => strcmp($codeA, $codeB) ?: strcmp($nameA, $nameB),
                'code_desc'   => strcmp($codeB, $codeA) ?: strcmp($nameA, $nameB),
                'type_asc'    => strcmp($typeA, $typeB) ?: strcmp($nameA, $nameB),
                'type_desc'   => strcmp($typeB, $typeA) ?: strcmp($nameA, $nameB),
                'status_asc'  => $statA <=> $statB ?: strcmp($nameA, $nameB),
                'status_desc' => $statB <=> $statA ?: strcmp($nameA, $nameB),
                default       => strcmp($nameA, $nameB),
            };
        });
        return $all;
    }

    public function getStoreMembers(int $storeId): array
    {
        $memberships = $this->storeUsers->findByStore($storeId);
        $users = [];
        foreach ($memberships as $m) {
            $u = $this->users->findById((int) $m['user_id']);
            if ($u) {
                $users[] = array_merge($u, ['membership' => $m]);
            }
        }
        return $users;
    }

    public function getStoreMembersMap(int $storeId): array
    {
        $map = [];
        foreach ($this->getStoreMembers($storeId) as $m) {
            $map[(int) $m['id']] = $m;
        }
        return $map;
    }

    public function createStore(array $data): array
    {
        $validator = new StoreValidator($this->languages);
        $validator->validate($data)->throwIfInvalid();

        return $this->stores->save([
            'code'            => strtoupper(trim($data['code'] ?? '')),
            'name'            => $data['name'] ?? '',
            'type'            => $data['type'] ?? 'retail',
            'timezone'        => $data['timezone'] ?? 'UTC',
            'locale'          => $data['locale'] ?? 'en',
            'currency'        => strtoupper(trim($data['currency'] ?? 'EUR')),
            'phone'           => ($data['phone'] ?? '') ?: null,
            'email'           => ($data['email'] ?? '') ?: null,
            'address_street'  => ($data['address_street'] ?? '') ?: null,
            'address_city'    => ($data['address_city'] ?? '') ?: null,
            'address_postal'  => ($data['address_postal'] ?? '') ?: null,
            'address_country' => ($data['address_country'] ?? '') ?: null,
            'is_active'       => 1,
        ]);
    }

    public function updateStore(int $storeId, array $data): array
    {
        $store = $this->stores->findById($storeId);
        if ($store === null) {
            throw new NotFoundException('Store introuvable.');
        }

        $storeData = array_merge($store, [
            'code'                 => strtoupper(trim($data['code'] ?? $store['code'] ?? '')),
            'name'                 => $data['name'] ?? $store['name'] ?? '',
            'type'                 => $data['type'] ?? $store['type'] ?? 'retail',
            'timezone'             => $data['timezone'] ?? $store['timezone'] ?? 'UTC',
            'locale'               => $data['locale'] ?? $store['locale'] ?? 'en',
            'currency'             => strtoupper(trim($data['currency'] ?? $store['currency'] ?? 'EUR')),
            'phone'                => ($data['phone'] ?? '') ?: null,
            'email'                => ($data['email'] ?? '') ?: null,
            'address_street'       => ($data['address_street'] ?? '') ?: null,
            'address_city'         => ($data['address_city'] ?? '') ?: null,
            'address_postal'       => ($data['address_postal'] ?? '') ?: null,
            'address_country'      => ($data['address_country'] ?? '') ?: null,
            'week_start_day'       => max(1, min(7, (int) ($data['week_start_day'] ?? 1))),
            'min_shift_minutes'    => (int) ($data['min_shift_minutes'] ?? 120),
            'max_shift_minutes'    => (int) ($data['max_shift_minutes'] ?? 480),
            'min_staff_per_day'    => (int) ($data['min_staff_per_day'] ?? 0),
            'low_staff_hour_start' => max(-1, min(23, (int) ($data['low_staff_hour_start'] ?? -1))),
            'low_staff_hour_end'   => max(-1, min(23, (int) ($data['low_staff_hour_end'] ?? -1))),
            'is_active'            => !empty($data['is_active']) ? 1 : 0,
        ]);

        if (isset($data['_excel_settings'])) {
            $this->stores->saveImportSettings($storeId, $data['_excel_settings']);
        }
        if (isset($data['_deduction_settings'])) {
            $this->stores->saveDeductionSettings($storeId, $data['_deduction_settings']);
        }
        if (isset($data['_features'])) {
            $this->stores->saveFeatures($storeId, $data['_features']);
        }

        return $this->stores->save($storeData);
    }

    public function deleteStore(int $storeId): void
    {
        $store = $this->stores->findById($storeId);
        if ($store === null) {
            throw new NotFoundException('Store introuvable.');
        }
        $this->stores->delete($storeId);
    }

    public function addMember(int $storeId, int $userId, string $role): ?array
    {
        $validator = new StoreValidator($this->languages);
        $validator->validateRole($role, self::ROLES)->throwIfInvalid();

        if ($this->storeUsers->findMembership($storeId, $userId) !== null) {
            return null;
        }

        return $this->storeUsers->save([
            'store_id' => $storeId,
            'user_id'  => $userId,
            'role'     => $role,
        ]);
    }

    public function updateMemberRole(int $membershipId, int $storeId, string $role): array
    {
        $membership = $this->storeUsers->findById($membershipId);
        if ($membership === null || (int) $membership['store_id'] !== $storeId) {
            throw new NotFoundException('Appartenance introuvable.');
        }

        $validator = new StoreValidator($this->languages);
        $validator->validateRole($role, self::ROLES)->throwIfInvalid();

        return $this->storeUsers->save(array_merge($membership, ['role' => $role]));
    }

    public function removeMember(int $membershipId, int $storeId): void
    {
        $membership = $this->storeUsers->findById($membershipId);
        if ($membership === null || (int) $membership['store_id'] !== $storeId) {
            throw new NotFoundException('Appartenance introuvable.');
        }
        $this->storeUsers->delete($membershipId);
    }

    public function saveMemberDeductions(int $membershipId, int $storeId, bool $subjectTo): void
    {
        $membership = $this->storeUsers->findById($membershipId);
        if ($membership === null || (int) $membership['store_id'] !== $storeId) {
            throw new NotFoundException('Membre introuvable.');
        }
        $this->storeUsers->setSubjectToDeductions($membershipId, $subjectTo);
    }

    public function getDeductionSettings(int $storeId): array
    {
        return $this->stores->getDeductionSettings($storeId);
    }

    public function getDeductionOverrides(int $membershipId): array
    {
        return ['subject_to_deductions' => $this->storeUsers->getSubjectToDeductions($membershipId)];
    }
}
