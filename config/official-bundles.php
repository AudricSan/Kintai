<?php

declare(strict_types=1);

/**
 * Slugs des bundles officiellement développés et maintenus par le projet
 * Kintai (voir Bundle::getName() de chaque classe pour la correspondance).
 *
 * Tout bundle découvert par BundleDiscoveryService (legacy dans src/Bundles/,
 * ou installé dynamiquement dans storage/bundles/) dont le slug n'apparaît
 * PAS ici est un bundle tiers — l'écran /admin/bundles/market affiche alors
 * un avertissement "non maintenu par le projet principal". Ce fichier ne fait
 * confiance à aucune auto-déclaration du bundle lui-même : seule cette liste
 * versionnée avec le core fait foi.
 *
 * "feedback" reste dans cette liste bien qu'il ne vive plus dans src/Bundles/ :
 * c'est le bundle pilote de la distribution façon add-on Home Assistant (voir
 * docs/architecture.md "Modular Bundles") — officiel malgré une distribution
 * désormais externe (dépôt kintai-bundle-feedback, registry officiel).
 */
return [
    'daily-report',
    'messaging',
    'store-photos',
    'timeoff',
    'shift-swap',
    'shift-claim',
    'resignation-report',
    'salary-report',
    'hiring-report',
    'feedback',
    'timeclock',
    'team-directory',
];
