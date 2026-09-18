<?php

declare(strict_types=1);

namespace kintai\Core;

/**
 * Alias de compatibilité : les bundles du monorepo (src/Bundles/*) étendent
 * encore cette classe historique le temps de leur migration progressive vers
 * le nouveau modèle "un repo par bundle". Le contrat réel, gelé et versionné
 * indépendamment, vit désormais dans kintai\Core\BundleContract\Bundle — un
 * nouveau bundle (legacy ou installé dynamiquement) doit étendre celui-ci
 * directement plutôt que cet alias.
 *
 * @see \kintai\Core\BundleContract\Bundle
 */
abstract class Bundle extends BundleContract\Bundle
{
}
