<?php

declare(strict_types=1);

namespace kintai\Tests\Support;

/**
 * Vérifie qu'un bundle n'utilise du Core que le contrat de bundle (kintai\Contract\…), plus une liste explicite de
 * classes « en attente d'extraction » : celles qui appartiennent au bundle mais vivent encore dans le Core
 * (dépôts, modèles et services propres au bundle, déplacés dans son dépôt à l'étape 4 du plan du contrat).
 *
 * Utilisé par la CI de chaque bundle (ContractUsageTest) : le socle de tests des bundles charge tests/Support du Core.
 */
final class ContractUsageScanner
{
    /**
     * @param list<string> $paths            Fichiers ou dossiers à analyser (src/, Views/, routes.php…)
     * @param list<string> $pendingExtraction Classes du Core encore tolérées (noms complets, sans « \ » initial)
     * @return list<string> Violations « fichier:ligne : classe »
     */
    public static function violations(array $paths, array $pendingExtraction = []): array
    {
        $violations = [];
        foreach (self::phpFiles($paths) as $file) {
            foreach (file($file) ?: [] as $i => $line) {
                if (preg_match_all('/\\\\?\b(kintai\\\\(?:Core|UI|Domain)\\\\[A-Za-z0-9_\\\\]+)/', $line, $m) === 0) {
                    continue;
                }
                foreach ($m[1] as $class) {
                    $class = rtrim($class, '\\');
                    if (!in_array($class, $pendingExtraction, true)) {
                        $violations[] = str_replace('\\', '/', $file) . ':' . ($i + 1) . ' : ' . $class;
                    }
                }
            }
        }
        return $violations;
    }

    /** @return list<string> */
    private static function phpFiles(array $paths): array
    {
        $files = [];
        foreach ($paths as $path) {
            if (is_file($path)) {
                $files[] = $path;
                continue;
            }
            if (!is_dir($path)) {
                continue;
            }
            $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS));
            foreach ($it as $f) {
                if ($f->isFile() && str_ends_with($f->getFilename(), '.php')) {
                    $files[] = $f->getPathname();
                }
            }
        }
        sort($files);
        return $files;
    }
}
