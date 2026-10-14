<?php

declare(strict_types=1);

namespace kintai\Core\Extension\Legacy;

use kintai\Contract\Provider\PendingRequestsProvider;
use kintai\Core\Container;

/** Base des fournisseurs historiques de LegacyPendingRequests : accès paresseux aux dépôts du conteneur. */
abstract class LegacyProvider implements PendingRequestsProvider
{
    public function __construct(protected readonly Container $container) {}
}
