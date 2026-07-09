<?php

declare(strict_types=1);

namespace kintai\Core\Services;

use kintai\Core\Exceptions\NotFoundException;

/**
 * Rend en HTML les pages du wiki GitHub (clone local .wiki/, gitignoré) pour
 * une consultation directe dans l'application.
 */
final class WikiContentService
{
    /**
     * Catalogue des guides : fichiers .wiki/ et titres affichés, par langue.
     */
    private const GUIDES = [
        'fr' => [
            'Quick-Start.md'      => 'Démarrage rapide',
            'Guide-Owner-FR.md'   => 'Guide Propriétaire',
            'Guide-Manager-FR.md' => 'Guide Responsable',
            'Guide-Staff-FR.md'   => 'Guide Personnel',
            'FAQ.md'              => 'Questions fréquentes (FAQ)',
            'Glossary.md'         => 'Glossaire',
            'Site-Map.md'         => 'Plan du site',
        ],
        'en' => [
            'Quick-Start-EN.md'    => 'Quick Start',
            'Guide-Owner-EN.md'    => 'Owner Guide',
            'Guide-Manager-EN.md'  => 'Manager Guide',
            'Guide-Staff-EN.md'    => 'Staff Guide',
            'FAQ-EN.md'            => 'Frequently Asked Questions (FAQ)',
            'Glossary-EN.md'       => 'Glossary',
            'Site-Map-EN.md'       => 'Site Map',
        ],
        'ja' => [
            'Quick-Start-JA.md'    => 'クイックスタート',
            'Guide-Owner-JA.md'    => 'オーナーガイド',
            'Guide-Manager-JA.md'  => 'マネージャーガイド',
            'Guide-Staff-JA.md'    => 'スタッフガイド',
            'FAQ-JA.md'            => 'よくある質問 (FAQ)',
            'Glossary-JA.md'       => '用語集',
            'Site-Map-JA.md'       => 'サイトマップ',
        ],
    ];

    private string $wikiPath;

    public function __construct(string $wikiPath = '')
    {
        $basePath = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 3);
        $this->wikiPath = $wikiPath !== '' ? $wikiPath : $basePath . '/.wiki';
    }

    /**
     * @return string[]
     */
    public static function supportedLangs(): array
    {
        return array_keys(self::GUIDES);
    }

    public function isConfigured(): bool
    {
        return is_dir($this->wikiPath);
    }

    /**
     * Table des matières (fichier + titre) pour une langue donnée.
     *
     * @return array{file: string, title: string}[]
     */
    public function tableOfContents(string $lang): array
    {
        $guide = self::GUIDES[$lang] ?? null;

        if ($guide === null) {
            return [];
        }

        $toc = [];
        foreach ($guide as $file => $title) {
            $toc[] = ['file' => $file, 'title' => $title];
        }

        return $toc;
    }

    public function firstPage(string $lang): ?string
    {
        $files = array_keys(self::GUIDES[$lang] ?? []);

        return $files[0] ?? null;
    }

    public function pageExists(string $lang, string $file): bool
    {
        if (!isset(self::GUIDES[$lang][$file])) {
            return false;
        }

        return file_exists($this->wikiPath . '/' . $file);
    }

    /**
     * Rend le contenu HTML d'une page du wiki.
     *
     * @throws NotFoundException si la langue/page est inconnue ou le fichier absent
     */
    public function render(string $lang, string $file): string
    {
        if (!$this->pageExists($lang, $file)) {
            throw new NotFoundException("Page de documentation introuvable : {$file}");
        }

        $raw = file_get_contents($this->wikiPath . '/' . $file);

        if ($raw === false) {
            throw new NotFoundException("Impossible de lire la page : {$file}");
        }

        $markdown  = $this->linkifyInternalPages($raw, $lang);
        $parsedown = new \Parsedown();
        $parsedown->setSafeMode(false);

        return $parsedown->text($markdown);
    }

    /**
     * Réécrit les liens Markdown internes (vers un autre fichier du même guide)
     * en liens vers les routes /docs/{lang}/{page} de l'application. Les liens
     * externes (http/https/mailto) sont laissés tels quels ; les liens internes
     * non reconnus sont dégradés en texte simple.
     */
    private function linkifyInternalPages(string $md, string $lang): string
    {
        $files = array_keys(self::GUIDES[$lang] ?? []);

        return preg_replace_callback(
            '/\[([^\]]+)\]\(([^)]+)\)/',
            static function (array $m) use ($files, $lang): string {
                [$full, $label, $target] = $m;

                if (preg_match('/^https?:|^mailto:/', $target)) {
                    return $full;
                }

                $targetBase = basename(parse_url($target, PHP_URL_PATH) ?: $target);
                if (!str_ends_with(strtolower($targetBase), '.md')) {
                    $targetBase .= '.md';
                }

                foreach ($files as $file) {
                    if (strcasecmp($file, $targetBase) === 0) {
                        $url = route_url('docs.show', ['lang' => $lang, 'page' => $file]);
                        return '[' . $label . '](' . $url . ')';
                    }
                }

                return $label;
            },
            $md
        );
    }
}
