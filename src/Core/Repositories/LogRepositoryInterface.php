<?php

declare(strict_types=1);

namespace kintai\Core\Repositories;

interface LogRepositoryInterface
{
    /** Niveaux de log standards */
    public const LEVEL_DEBUG    = 'debug';
    public const LEVEL_INFO     = 'info';
    public const LEVEL_WARNING  = 'warning';
    public const LEVEL_ERROR    = 'error';
    public const LEVEL_CRITICAL = 'critical';

    /** Canaux de log */
    public const CHANNEL_AUDIT    = 'audit';
    public const CHANNEL_ERROR    = 'error';
    public const CHANNEL_ACCESS   = 'access';
    public const CHANNEL_BUSINESS = 'business';

    /** Insère une entrée de log simple. */
    public function log(string $level, string $channel, string $message, array $context = [], ?int $userId = null, ?int $storeId = null, ?string $ipAddress = null, ?string $userAgent = null): void;

    /** Insère une entrée enrichie (action, ressource, contexte requête). */
    public function record(string $level, string $channel, string $message, ?string $action = null, ?string $resourceType = null, ?int $resourceId = null, array $context = [], ?int $userId = null, ?int $storeId = null, ?string $ipAddress = null, ?string $userAgent = null, ?string $requestMethod = null, ?string $requestUri = null, ?int $responseStatus = null, ?int $durationMs = null, ?string $sessionId = null): void;

    /** Retourne les logs paginés avec filtres optionnels. */
    public function findAll(int $page = 1, int $perPage = 50, array $filters = []): array;

    /** Retourne le nombre total de logs correspondant aux filtres. */
    public function countAll(array $filters = []): int;

    /** Retourne un log par son ID. */
    public function findById(int $id): ?array;

    /** Retourne les canaux disponibles (distincts). */
    public function findChannels(): array;

    /** Retourne les actions distinctes (pour les filtres). */
    public function findActions(): array;

    /** Retourne les types de ressource distincts (pour les filtres). */
    public function findResourceTypes(): array;
}
