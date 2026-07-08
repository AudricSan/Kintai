<?php

declare(strict_types=1);

namespace kintai\Core\Services;

use kintai\Core\Request;

final class AuditLogger
{
    public function log(
        Request $request,
        string $action,
        string $resourceType,
        ?int $resourceId = null,
        array $details = [],
        ?int $storeId = null,
        ?int $userId = null,
    ): void {
        Log::record(
            $action,
            $resourceType,
            $resourceId,
            $action,
            $details,
            $userId,
            $storeId,
        );
    }

    public function logUpdate(
        Request $request,
        string $action,
        string $resourceType,
        ?int $resourceId,
        array $oldData,
        array $newData,
        array $extraContext = [],
        ?int $storeId = null,
        ?int $userId = null,
    ): void {
        $changes = DiffHelper::diff($oldData, $newData);

        $context = $extraContext;
        if ($changes !== []) {
            $context['changes'] = $changes;
        }

        Log::record(
            $action,
            $resourceType,
            $resourceId,
            $action,
            $context,
            $userId,
            $storeId,
        );
    }
}
