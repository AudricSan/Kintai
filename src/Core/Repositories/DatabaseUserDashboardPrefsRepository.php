<?php
declare(strict_types=1);

namespace kintai\Core\Repositories;

use kintai\Domain\Eloquent\UserDashboardPref as EloquentUserDashboardPref;

final class DatabaseUserDashboardPrefsRepository implements UserDashboardPrefsRepositoryInterface
{
    public function __construct() {}

    public function getEnabledWidgets(int $userId, string $dashboardType): ?array
    {
        $record = EloquentUserDashboardPref::where('user_id', $userId)
            ->where('dashboard_type', $dashboardType)
            ->first();
        if ($record === null) {
            return null;
        }
        $decoded = json_decode($record['widgets'] ?? '[]', true);
        return is_array($decoded) ? $decoded : null;
    }

    public function saveWidgets(int $userId, array $widgets, string $dashboardType): void
    {
        $data = [
            'user_id'        => $userId,
            'dashboard_type' => $dashboardType,
            'widgets'        => json_encode(array_values($widgets)),
            'updated_at'     => date('Y-m-d H:i:s'),
        ];

        EloquentUserDashboardPref::updateOrCreate(
            ['user_id' => $userId, 'dashboard_type' => $dashboardType],
            $data,
        );
    }
}
