<?php

declare(strict_types=1);

/**
 * Wrapper CLI pour la génération des guides Kintai en PDF (FR / EN / JA).
 *
 * Usage :
 *   php scripts/pdf.php                    → génère les 3 PDFs
 *   php scripts/pdf.php --lang=fr          → FR uniquement
 *   php scripts/pdf.php --lang=en          → EN uniquement
 *   php scripts/pdf.php --lang=ja          → JA uniquement
 *   php scripts/pdf.php --output=/chemin/  → répertoire de sortie personnalisé
 */

define('BASE_PATH', dirname(__DIR__));

require BASE_PATH . '/vendor/autoload.php';
require BASE_PATH . '/src/Core/helpers.php';

use kintai\Core\Services\WikiPdfGeneratorService;

$opts       = getopt('', ['lang:', 'output:']);
$langFilter = isset($opts['lang']) ? strtolower(trim((string) $opts['lang'])) : null;
$outputDir  = isset($opts['output']) ? rtrim((string) $opts['output'], '/\\') : BASE_PATH . '/public/pdf';

$supported = WikiPdfGeneratorService::supportedLangs();

if ($langFilter !== null && !in_array($langFilter, $supported, true)) {
    fwrite(STDERR, "Langue invalide : « {$langFilter} ». Valeurs acceptées : " . implode(', ', $supported) . "\n");
    exit(1);
}

echo "Répertoire de sortie : {$outputDir}\n\n";

$service = new WikiPdfGeneratorService();
$results = ($langFilter !== null)
    ? [$langFilter => $service->generate($langFilter, $outputDir)]
    : $service->generateAll($outputDir);

$exitCode = 0;

foreach ($results as $lang => $res) {
    if ($res['ok']) {
        echo "  ✓ {$res['filename']} ({$res['size_kb']} Ko)\n";
    } else {
        echo "  ✗ [{$lang}] {$res['error']}\n";
        $exitCode = 1;
    }
}

echo "\nTerminé.\n";
exit($exitCode);
