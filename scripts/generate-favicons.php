<?php

/**
 * Génère le jeu de favicons de l'app à partir du logo carré existant
 * (public/assets/img/kintai-512.png, déjà utilisé comme icône PWA).
 *
 * Usage :
 *   php scripts/generate-favicons.php
 *
 * Script one-shot (pas exécuté au runtime) : à relancer manuellement si le
 * logo source change. GD n'ayant pas d'encodeur ICO natif, favicon.ico est
 * écrit à la main (en-tête ICONDIR/ICONDIRENTRY + frames PNG embarquées,
 * format supporté nativement par tous les navigateurs/OS modernes).
 */

declare(strict_types=1);

$sourcePath = dirname(__DIR__) . '/public/assets/img/kintai-512.png';
$outputDir  = dirname(__DIR__) . '/public/assets/img/';

if (!is_file($sourcePath)) {
    fwrite(STDERR, "Source introuvable : {$sourcePath}\n");
    exit(1);
}

$source = imagecreatefrompng($sourcePath);
if (!$source instanceof \GdImage) {
    fwrite(STDERR, "Impossible de décoder {$sourcePath}\n");
    exit(1);
}

/** Redimensionne $source vers un carré $size×$size et l'écrit en PNG. */
function renderSquarePng(\GdImage $source, int $size): \GdImage
{
    $resized = imagecreatetruecolor($size, $size);
    imagealphablending($resized, false);
    imagesavealpha($resized, true);
    $transparent = imagecolorallocatealpha($resized, 0, 0, 0, 127);
    imagefill($resized, 0, 0, $transparent);

    imagecopyresampled($resized, $source, 0, 0, 0, 0, $size, $size, imagesx($source), imagesy($source));

    return $resized;
}

/** Assemble un .ico à partir de plusieurs images GD, une frame PNG par taille. */
function writeIco(array $images, string $destPath): void
{
    $count = count($images);
    $header = pack('vvv', 0, 1, $count);

    $entries = '';
    $data    = '';
    $offset  = 6 + $count * 16;

    foreach ($images as $image) {
        $size = imagesx($image);

        ob_start();
        imagepng($image);
        $png = (string) ob_get_clean();

        $dim = $size >= 256 ? 0 : $size; // 0 = 256px en encodage ICO
        $entries .= pack('CCCCvvVV', $dim, $dim, 0, 0, 1, 32, strlen($png), $offset);
        $data    .= $png;
        $offset  += strlen($png);
    }

    file_put_contents($destPath, $header . $entries . $data);
}

$sizes = [16 => 'favicon-16.png', 32 => 'favicon-32.png', 180 => 'apple-touch-icon.png'];
$icoSizes = [16, 32, 48];
$icoImages = [];

foreach ($sizes as $size => $filename) {
    $resized = renderSquarePng($source, $size);
    imagepng($resized, $outputDir . $filename, 9);
    echo "  - {$filename} ({$size}x{$size})\n";

    if (in_array($size, $icoSizes, true)) {
        $icoImages[$size] = $resized;
    } else {
        imagedestroy($resized);
    }
}

foreach ($icoSizes as $size) {
    if (!isset($icoImages[$size])) {
        $icoImages[$size] = renderSquarePng($source, $size);
    }
}
ksort($icoImages);

writeIco(array_values($icoImages), $outputDir . 'favicon.ico');
echo "  - favicon.ico (" . implode('/', $icoSizes) . ")\n";

foreach ($icoImages as $image) {
    imagedestroy($image);
}
imagedestroy($source);

echo "Favicons générés dans {$outputDir}\n";
