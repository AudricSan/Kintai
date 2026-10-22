<?php

declare(strict_types=1);

namespace kintai\Core\Services;

/**
 * Contrôle d'un dossier de wiki (clone de Kintai.wiki.git ou miroir `.wiki/`) avant publication :
 *  - chaque page de _Sidebar.md existe dans les trois langues (anglais à la racine, `fr/`, `ja/`) ;
 *  - chaque page porte la ligne de choix de langue ;
 *  - tout lien interne (page ou image) pointe vers un fichier qui existe ;
 *  - signale les pages qui ne figurent pas dans la barre latérale (hors pages-relais d'anciens liens).
 *
 * Ne modifie rien. Voir `php scripts/check-wiki.php <dossier>`.
 */
final class WikiIntegrityChecker
{
    /** Une page de moins de lignes non vides que ceci est un relais d'ancien lien : pas listée dans la barre latérale. */
    private const STUB_MAX_LINES = 6;

    /**
     * @return array{errors: list<string>, warnings: list<string>}
     */
    public function check(string $wikiPath): array
    {
        $errors   = [];
        $warnings = [];
        $wikiPath = rtrim(str_replace('\\', '/', $wikiPath), '/');

        $sidebarFile = $wikiPath . '/_Sidebar.md';
        if (!is_file($sidebarFile)) {
            return ['errors' => ['_Sidebar.md introuvable'], 'warnings' => []];
        }

        $groups = WikiContentService::parseSidebarMarkdown((string) file_get_contents($sidebarFile));
        $listed = [];
        foreach ($groups as $slugs) {
            foreach ($slugs as $slug) {
                $listed[$slug] = true;
            }
        }

        $dirs = ['' => 'en', 'fr/' => 'fr', 'ja/' => 'ja'];

        // Nom de fichier d'une page dans chaque langue : celui que déclare la ligne de langue de sa version anglaise
        // (sinon le slug lui-même), et correspondance inverse nom traduit => slug anglais.
        $localName = [];
        $keyOf     = ['fr' => [], 'ja' => []];
        foreach (glob("$wikiPath/*.md") ?: [] as $file) {
            $slug = basename($file, '.md');
            if ($slug[0] === '_') {
                continue;
            }
            foreach (WikiContentService::localizedSlugsFromPage((string) file_get_contents($file)) as $lang => $local) {
                $localName[$lang][$slug] = $local;
                $keyOf[$lang][$local]    = $slug;
            }
        }

        foreach (array_keys($listed) as $slug) {
            foreach ($dirs as $dir => $lang) {
                $file = $lang === 'en' ? $slug : ($localName[$lang][$slug] ?? $slug);
                if (!is_file("$wikiPath/{$dir}$file.md")) {
                    $errors[] = "page manquante ($lang) : $slug" . ($file !== $slug ? " ($file)" : '');
                }
            }
        }

        foreach ($dirs as $dir => $lang) {
            foreach (glob("$wikiPath/{$dir}*.md") ?: [] as $file) {
                $name = basename($file, '.md');
                if ($name[0] === '_') {
                    continue;
                }
                $key     = $lang === 'en' ? $name : ($keyOf[$lang][$name] ?? $name);
                $content = str_replace("\r\n", "\n", (string) file_get_contents($file));
                $rel     = "{$dir}$name";

                if (!str_contains($content, '🌐')) {
                    $errors[] = "ligne de choix de langue absente : $rel";
                }
                if (!isset($listed[$key]) && $this->nonEmptyLines($content) > self::STUB_MAX_LINES) {
                    $warnings[] = "page absente de la barre latérale ($lang) : $name";
                }
                foreach ($this->brokenLinks($wikiPath, $dir, $content) as $target) {
                    $errors[] = "lien cassé dans $rel : $target";
                }
            }
        }

        // Une page présente dans une langue mais pas dans les autres (hors barre latérale).
        $names = [];
        foreach ($dirs as $dir => $lang) {
            foreach (glob("$wikiPath/{$dir}*.md") ?: [] as $file) {
                $name = basename($file, '.md');
                if ($name[0] !== '_') {
                    $key = $lang === 'en' ? $name : ($keyOf[$lang][$name] ?? $name);
                    $names[$key][$lang] = true;
                }
            }
        }
        foreach ($names as $name => $langs) {
            foreach (['en', 'fr', 'ja'] as $lang) {
                if (!isset($langs[$lang]) && !isset($listed[$name])) {
                    $errors[] = "traduction manquante ($lang) : $name";
                }
            }
        }

        sort($errors);
        sort($warnings);
        return ['errors' => array_values(array_unique($errors)), 'warnings' => $warnings];
    }

    private function nonEmptyLines(string $content): int
    {
        return count(array_filter(explode("\n", $content), static fn(string $l) => trim($l) !== ''));
    }

    /** @return list<string> cibles de liens internes qui ne correspondent à aucun fichier */
    private function brokenLinks(string $wikiPath, string $dir, string $content): array
    {
        // Les blocs de code ne contiennent pas de liens (exemples de syntaxe, adresses).
        $content = (string) preg_replace('/```.*?```/s', '', $content);
        $content = (string) preg_replace('/`[^`\n]*`/', '', $content);

        $broken = [];
        if (preg_match_all('/\]\(([^)\s]+)\)/', $content, $m) === false) {
            return [];
        }
        foreach ($m[1] as $href) {
            if (preg_match('#^(https?:|mailto:|tel:|//)#i', $href) === 1 || $href[0] === '#') {
                continue;
            }
            $path = preg_replace('/[#?].*$/', '', $href);
            if ($path === '' || $path === null) {
                continue;
            }
            $segments = explode('/', ($dir === '' ? '' : rtrim($dir, '/') . '/') . $path);
            $resolved = [];
            foreach ($segments as $seg) {
                if ($seg === '..') {
                    array_pop($resolved);
                } elseif ($seg !== '.' && $seg !== '') {
                    $resolved[] = $seg;
                }
            }
            $target = implode('/', $resolved);
            $hasExt = pathinfo($target, PATHINFO_EXTENSION) !== '';
            if (!is_file("$wikiPath/" . ($hasExt ? $target : $target . '.md'))) {
                $broken[] = $href;
            }
        }
        return array_values(array_unique($broken));
    }
}
