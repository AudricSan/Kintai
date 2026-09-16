<?php

declare(strict_types=1);


namespace kintai\Core\Repositories;

interface UserDashboardPrefsRepositoryInterface
{
    /**
     * Returns saved widget keys for the given user+dashboard, or null if no
     * preferences have been saved yet (caller should fall back to its own defaults).
     *
     * @return string[]|null
     */
    public function getEnabledWidgets(int $userId, string $dashboardType): ?array;

    /** @param string[] $widgets */
    public function saveWidgets(int $userId, array $widgets, string $dashboardType): void;
}
