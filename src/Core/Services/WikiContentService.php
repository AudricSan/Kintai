<?php

declare(strict_types=1);

namespace kintai\Core\Services;

use kintai\Core\Exceptions\NotFoundException;

/**
 * Rend en HTML les pages du wiki GitHub (clone local .wiki/) pour une
 * consultation directe dans l'application, en s'appuyant sur le catalogue
 * de guides défini par WikiPdfGeneratorService.
 */
final class WikiContentService
{
    private string $wikiPath;

    public function __construct(string $wikiPath = '')
    {
        $basePath = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 3);
        $this->wikiPath = $wikiPath !== '' ? $wikiPath : $basePath . '/.wiki';
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
        $guide = WikiPdfGeneratorService::guides()[$lang] ?? null;

        if ($guide === null) {
            return [];
        }

        return array_map(
            static fn (string $file): array => [
                'file'  => $file,
                'title' => WikiPdfGeneratorService::sectionTitle($lang, $file),
            ],
            $guide['files']
        );
    }

    public function firstPage(string $lang): ?string
    {
        return WikiPdfGeneratorService::guides()[$lang]['files'][0] ?? null;
    }

    public function pageExists(string $lang, string $file): bool
    {
        $guide = WikiPdfGeneratorService::guides()[$lang] ?? null;

        if ($guide === null || !in_array($file, $guide['files'], true)) {
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
        $files = WikiPdfGeneratorService::guides()[$lang]['files'] ?? [];

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
