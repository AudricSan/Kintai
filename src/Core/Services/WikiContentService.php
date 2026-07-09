<?php

declare(strict_types=1);

namespace kintai\Core\Services;

use kintai\Core\Exceptions\NotFoundException;

/**
 * Rend en HTML les pages du wiki GitHub (clone local .wiki/, gitignoré) pour
 * une consultation directe dans l'application.
 *
 * Structure attendue (celle du wiki GitHub réel du projet) :
 *   .wiki/Home.md, .wiki/Installation.md, ...   (anglais, à la racine)
 *   .wiki/fr/Home.md, .wiki/ja/Home.md, ...      (traductions, en sous-dossier)
 *   .wiki/_Sidebar.md                            (navigation, format wiki GitHub)
 *
 * Les liens internes entre pages du wiki utilisent la convention GitHub Wiki :
 * chemins relatifs sans extension ".md" (ex: "Installation", "../Home",
 * "../ja/Home" depuis une page sous fr/).
 */
final class WikiContentService
{
    /** @var array<string, string> Langue => sous-dossier ('' pour l'anglais, à la racine) */
    private const LANG_DIRS = [
        'fr' => 'fr',
        'en' => '',
        'ja' => 'ja',
    ];

    /** @var array<string, string> Libellé de groupe (tel qu'écrit dans _Sidebar.md) => clé de traduction */
    private const GROUP_LABEL_KEYS = [
        'Getting Started'        => 'docs_group_getting_started',
        'Concepts & Architecture' => 'docs_group_concepts',
        'User Guide'              => 'docs_group_user_guide',
        'Reference'                => 'docs_group_reference',
    ];

    /** Groupe synthétique (avant tout titre de section dans _Sidebar.md, ex: Home) */
    private const TOP_GROUP = '';

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
        return array_keys(self::LANG_DIRS);
    }

    public function isConfigured(): bool
    {
        return is_dir($this->wikiPath);
    }

    /**
     * URL de la page équivalente sur le wiki GitHub en ligne, consultable même
     * quand `.wiki/` n'est pas cloné localement (dépôt configuré via la même
     * variable d'environnement que la vérification de mises à jour, puisque
     * c'est le même dépôt canonique).
     */
    public function githubWikiUrl(string $lang, ?string $slug = null): string
    {
        $repo = env('GITHUB_UPDATE_REPO', 'AudricSan/Kintai');
        $dir  = self::LANG_DIRS[$lang] ?? '';
        $slug = ($slug !== null && $this->isValidSlug($slug)) ? $slug : 'Home';
        $path = $dir !== '' ? $dir . '/' . $slug : $slug;

        return 'https://github.com/' . $repo . '/wiki/' . $path;
    }

    /**
     * Table des matières groupée (telle que définie par _Sidebar.md), avec le
     * titre, la disponibilité locale et le lien GitHub de chaque page dans la
     * langue donnée (ce dernier permet d'y accéder même si la page n'est pas
     * disponible localement).
     *
     * @return array{label: string|null, items: array{slug: string, title: string, available: bool, github_url: string}[]}[]
     */
    public function tableOfContents(string $lang): array
    {
        $toc = [];

        foreach ($this->parseSidebar() as $groupName => $slugs) {
            $items = [];
            foreach ($slugs as $slug) {
                $items[] = [
                    'slug'       => $slug,
                    'title'      => $this->pageTitle($lang, $slug),
                    'available'  => $this->pageExists($lang, $slug),
                    'github_url' => $this->githubWikiUrl($lang, $slug),
                ];
            }

            $labelKey = self::GROUP_LABEL_KEYS[$groupName] ?? null;

            $toc[] = [
                'label' => $groupName === self::TOP_GROUP
                    ? null
                    : ($labelKey !== null ? __($labelKey) : $groupName),
                'items' => $items,
            ];
        }

        return $toc;
    }

    /**
     * Première page à afficher (page d'accueil du wiki).
     */
    public function firstPage(): ?string
    {
        foreach ($this->parseSidebar() as $slugs) {
            if ($slugs !== []) {
                return $slugs[0];
            }
        }

        return null;
    }

    public function pageExists(string $lang, string $slug): bool
    {
        if (!$this->isValidSlug($slug) || !isset(self::LANG_DIRS[$lang])) {
            return false;
        }

        return file_exists($this->resolvePath($lang, $slug));
    }

    /**
     * Rend le contenu HTML d'une page du wiki.
     *
     * @throws NotFoundException si la langue/page est inconnue ou le fichier absent
     */
    public function render(string $lang, string $slug): string
    {
        if (!$this->pageExists($lang, $slug)) {
            throw new NotFoundException("Page de documentation introuvable : {$slug}");
        }

        $raw = file_get_contents($this->resolvePath($lang, $slug));

        if ($raw === false) {
            throw new NotFoundException("Impossible de lire la page : {$slug}");
        }

        $markdown  = $this->linkifyInternalPages($this->stripLanguageSwitchLine($raw), $lang);
        $parsedown = new \Parsedown();
        $parsedown->setSafeMode(false);

        return $parsedown->text($markdown);
    }

    // -------------------------------------------------------------------------
    // Résolution de chemin / slug
    // -------------------------------------------------------------------------

    private function resolvePath(string $lang, string $slug): string
    {
        $dir = self::LANG_DIRS[$lang];

        return $this->wikiPath . ($dir !== '' ? '/' . $dir : '') . '/' . $slug . '.md';
    }

    /**
     * Slugs de pages wiki : lettres/chiffres/tirets/underscores, jamais de
     * chemin ("/", "..") ni de page méta ("_Sidebar", "_Footer").
     */
    private function isValidSlug(string $slug): bool
    {
        return $slug !== '' && $slug[0] !== '_' && preg_match('/^[A-Za-z0-9_-]+$/', $slug) === 1;
    }

    // -------------------------------------------------------------------------
    // _Sidebar.md → structure groupée de slugs
    // -------------------------------------------------------------------------

    /**
     * @return array<string, string[]> Nom de groupe ("" pour le sommet) => slugs
     */
    private function parseSidebar(): array
    {
        $path = $this->wikiPath . '/_Sidebar.md';

        if (!file_exists($path)) {
            return [];
        }

        $raw = (string) file_get_contents($path);
        $groups  = [];
        $current = self::TOP_GROUP;

        foreach (explode("\n", $raw) as $line) {
            $line = trim($line);

            if ($line === '' || str_contains($line, '🌐')) {
                continue;
            }

            // En-tête de groupe : **Texte** (et non un lien **[Texte](Cible)**)
            if (preg_match('/^\*\*([^\[*][^*]*)\*\*$/', $line, $m)) {
                $current = trim($m[1]);
                $groups[$current] ??= [];
                continue;
            }

            if (preg_match('/\[([^\]]+)\]\(([^)]+)\)/', $line, $m)) {
                $slug = trim($m[2]);
                if ($this->isValidSlug($slug)) {
                    $groups[$current] ??= [];
                    $groups[$current][] = $slug;
                }
            }
        }

        return $groups;
    }

    // -------------------------------------------------------------------------
    // Rendu du contenu d'une page
    // -------------------------------------------------------------------------

    /**
     * Supprime la ligne de sélection de langue générée par le wiki
     * (ex: "🌐 **English** · [Français](fr/Home) · [日本語](ja/Home)") :
     * l'application affiche son propre sélecteur de langue.
     */
    private function stripLanguageSwitchLine(string $md): string
    {
        $lines = array_filter(
            explode("\n", $md),
            static fn (string $line): bool => !str_contains($line, '🌐')
        );

        return implode("\n", $lines);
    }

    /**
     * Réécrit les liens Markdown internes du wiki (chemins relatifs façon
     * GitHub Wiki, ex: "Installation", "../Home", "../ja/Home") en liens vers
     * les routes /docs/{lang}/{page} de l'application. Les liens externes
     * (http/https/mailto) et les ancres ("#...") sont laissés tels quels ; les
     * liens internes qui ne résolvent vers aucune page connue sont dégradés en
     * texte simple.
     */
    private function linkifyInternalPages(string $md, string $lang): string
    {
        return preg_replace_callback(
            '/\[([^\]]+)\]\(([^)]+)\)/',
            function (array $m) use ($lang): string {
                [$full, $label, $target] = $m;

                if (preg_match('/^https?:|^mailto:|^#/', $target)) {
                    return $full;
                }

                [$resolvedLang, $resolvedSlug] = $this->resolveWikiLink($lang, $target);

                if ($resolvedLang !== null && $this->pageExists($resolvedLang, $resolvedSlug)) {
                    $url = route_url('docs.show', ['lang' => $resolvedLang, 'page' => $resolvedSlug]);
                    return '[' . $label . '](' . $url . ')';
                }

                return $label;
            },
            $md
        );
    }

    /**
     * Résout un lien relatif façon GitHub Wiki (depuis la page courante) vers
     * une paire [langue, slug]. Retourne [null, null] si non résoluble.
     *
     * @return array{0: string|null, 1: string|null}
     */
    private function resolveWikiLink(string $currentLang, string $target): array
    {
        $target = strtok($target, '#');
        $currentDir = self::LANG_DIRS[$currentLang] ?? '';
        $segments = $currentDir !== '' ? [$currentDir] : [];

        foreach (explode('/', trim((string) $target, '/')) as $part) {
            if ($part === '' || $part === '.') {
                continue;
            }
            if ($part === '..') {
                array_pop($segments);
                continue;
            }
            $segments[] = $part;
        }

        if (count($segments) === 1 && $this->isValidSlug($segments[0])) {
            return ['en', $segments[0]];
        }

        if (count($segments) === 2 && in_array($segments[0], ['fr', 'ja'], true) && $this->isValidSlug($segments[1])) {
            return [$segments[0], $segments[1]];
        }

        return [null, null];
    }

    /**
     * Titre affiché d'une page : premier titre H1 de son contenu (dans la
     * langue donnée, ou à défaut la version anglaise), sinon le slug humanisé.
     */
    private function pageTitle(string $lang, string $slug): string
    {
        $path = $this->resolvePath($lang, $slug);

        if (!file_exists($path)) {
            $path = $this->resolvePath('en', $slug);
        }

        if (file_exists($path)) {
            $raw = (string) file_get_contents($path);
            if (preg_match('/^#\s+(.+)$/m', $raw, $m)) {
                return trim($m[1]);
            }
        }

        return str_replace(['-', '_'], ' ', $slug);
    }
}
