<?php

declare(strict_types=1);

namespace kintai\Core\Services;

/**
 * Génère les guides utilisateur Kintai en PDF (FR / EN / JA) via mPDF.
 *
 * Appelable en HTTP (depuis WikiPdfController) et en CLI (scripts/pdf.php).
 */
final class WikiPdfGeneratorService
{
    private const GUIDES = [
        'fr' => [
            'title'    => 'Guide Utilisateur Kintai',
            'subtitle' => 'Manuel complet — Propriétaire · Responsable · Personnel',
            'version'  => 'Kintai v2.x',
            'lang_tag' => 'fr',
            'files'    => [
                'Quick-Start.md',
                'Guide-Owner-FR.md',
                'Guide-Manager-FR.md',
                'Guide-Staff-FR.md',
                'FAQ.md',
                'Glossary.md',
                'Site-Map.md',
            ],
            'output'   => 'Kintai-Guide-FR.pdf',
        ],
        'en' => [
            'title'    => 'Kintai User Guide',
            'subtitle' => 'Complete Manual — Owner · Manager · Staff',
            'version'  => 'Kintai v2.x',
            'lang_tag' => 'en',
            'files'    => [
                'Quick-Start-EN.md',
                'Guide-Owner-EN.md',
                'Guide-Manager-EN.md',
                'Guide-Staff-EN.md',
                'FAQ-EN.md',
                'Glossary-EN.md',
                'Site-Map-EN.md',
            ],
            'output'   => 'Kintai-Guide-EN.pdf',
        ],
        'ja' => [
            'title'    => 'Kintaiユーザーガイド',
            'subtitle' => '完全マニュアル — オーナー・マネージャー・スタッフ',
            'version'  => 'Kintai v2.x',
            'lang_tag' => 'ja',
            'files'    => [
                'Quick-Start-JA.md',
                'Guide-Owner-JA.md',
                'Guide-Manager-JA.md',
                'Guide-Staff-JA.md',
                'FAQ-JA.md',
                'Glossary-JA.md',
                'Site-Map-JA.md',
            ],
            'output'   => 'Kintai-Guide-JA.pdf',
        ],
    ];

    private string $wikiPath;
    private string $tmpDir;

    public function __construct(string $wikiPath = '', string $tmpDir = '')
    {
        $basePath = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 3);
        $this->wikiPath = $wikiPath !== '' ? $wikiPath : $basePath . '/.wiki';
        $this->tmpDir   = $tmpDir   !== '' ? $tmpDir   : storage_path('app/mpdf');

        if (!is_dir($this->tmpDir)) {
            mkdir($this->tmpDir, 0755, true);
        }
    }

    /**
     * Retourne la liste des langues supportées.
     *
     * @return string[]
     */
    public static function supportedLangs(): array
    {
        return array_keys(self::GUIDES);
    }

    /**
     * Retourne le catalogue des guides (fichiers .wiki/, titres, métadonnées),
     * réutilisé par WikiContentService pour la consultation en ligne.
     *
     * @return array<string, array{title: string, subtitle: string, version: string, lang_tag: string, files: string[], output: string}>
     */
    public static function guides(): array
    {
        return self::GUIDES;
    }

    /**
     * Titre affiché pour une page (.md) donnée, dans la langue donnée.
     */
    public static function sectionTitle(string $lang, string $file): string
    {
        $meta = self::labels()[$lang] ?? self::labels()['en'];

        return $meta['sections'][$file] ?? basename($file, '.md');
    }

    /**
     * Génère les PDFs pour toutes les langues.
     *
     * @return array<string, array{ok: bool, filename: string, size_kb: int, error: string}>
     */
    public function generateAll(string $outputDir = ''): array
    {
        $results = [];

        foreach (array_keys(self::GUIDES) as $lang) {
            $results[$lang] = $this->generate($lang, $outputDir);
        }

        return $results;
    }

    /**
     * Génère le PDF pour une langue donnée.
     *
     * @return array{ok: bool, filename: string, size_kb: int, error: string}
     */
    public function generate(string $lang, string $outputDir = ''): array
    {
        if (!isset(self::GUIDES[$lang])) {
            return ['ok' => false, 'filename' => '', 'size_kb' => 0, 'error' => "Langue inconnue : {$lang}"];
        }

        $guide     = self::GUIDES[$lang];
        $basePath  = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 3);
        $outputDir = ($outputDir !== '') ? rtrim($outputDir, '/\\') : $basePath . '/public/pdf';

        if (!is_dir($outputDir) && !mkdir($outputDir, 0755, true) && !is_dir($outputDir)) {
            return ['ok' => false, 'filename' => '', 'size_kb' => 0, 'error' => "Impossible de créer : {$outputDir}"];
        }

        $missingFiles = [];
        foreach ($guide['files'] as $file) {
            if (!file_exists($this->wikiPath . '/' . $file)) {
                $missingFiles[] = $file;
            }
        }

        if ($missingFiles !== []) {
            return [
                'ok'       => false,
                'filename' => $guide['output'],
                'size_kb'  => 0,
                'error'    => 'Fichiers manquants : ' . implode(', ', $missingFiles),
            ];
        }

        try {
            $parsedown = new \Parsedown();
            $parsedown->setSafeMode(false);

            $mpdf      = $this->buildMpdf($lang);
            $htmlParts = [$this->buildCoverPage($guide), $this->buildToc($guide)];

            foreach ($guide['files'] as $idx => $file) {
                $raw = file_get_contents($this->wikiPath . '/' . $file);
                if ($raw === false) {
                    throw new \RuntimeException("Impossible de lire : {$file}");
                }
                $markdown    = $this->preprocessMarkdown($raw);
                $sectionHtml = $parsedown->text($markdown);
                $anchor      = 'section-' . $idx;
                $htmlParts[] = '<div class="section-break"></div>' . "\n"
                             . '<a name="' . $anchor . '"></a>' . "\n"
                             . $sectionHtml;
            }

            $fullHtml = $this->buildFullHtml(implode("\n", $htmlParts), $guide);

            $mpdf->SetTitle($guide['title'] . ' — ' . $guide['version']);
            $mpdf->SetAuthor('Kintai');
            $mpdf->SetHTMLFooter($this->buildFooter($guide));

            $mpdf->WriteHTML($this->buildCss($lang), \Mpdf\HTMLParserMode::HEADER_CSS);
            $mpdf->WriteHTML($fullHtml);

            $outputPath = $outputDir . '/' . $guide['output'];
            $mpdf->Output($outputPath, \Mpdf\Output\Destination::FILE);

            $sizeKb = (int) round(filesize($outputPath) / 1024);

            return ['ok' => true, 'filename' => $guide['output'], 'size_kb' => $sizeKb, 'error' => ''];

        } catch (\Throwable $e) {
            return ['ok' => false, 'filename' => $guide['output'], 'size_kb' => 0, 'error' => $e->getMessage()];
        }
    }

    // -------------------------------------------------------------------------
    // Helpers de génération
    // -------------------------------------------------------------------------

    private function preprocessMarkdown(string $md): string
    {
        $lines = explode("\n", $md);
        $out   = [];

        foreach ($lines as $line) {
            if (preg_match('/^\s*>\s*🌐/', $line)) {
                continue;
            }

            $line = preg_replace_callback(
                '/\[([^\]]+)\]\(([^)]+)\)/',
                static function (array $m): string {
                    if (preg_match('/^https?:|^mailto:/', $m[2])) {
                        return $m[0];
                    }

                    return $m[1];
                },
                $line
            );

            $out[] = $line;
        }

        return implode("\n", $out);
    }

    private function buildMpdf(string $lang): \Mpdf\Mpdf
    {
        $config = [
            'mode'             => 'utf-8',
            'format'           => 'A4',
            'margin_left'      => 20,
            'margin_right'     => 20,
            'margin_top'       => 22,
            'margin_bottom'    => 20,
            'margin_header'    => 0,
            'margin_footer'    => 8,
            'tempDir'          => $this->tmpDir,
            'useSubstitutions' => true,
        ];

        $fontConfig = $this->findCjkFont();
        if ($fontConfig !== null) {
            $config['fontDir']  = $fontConfig['dir'];
            $config['fontdata'] = $fontConfig['data'];
            if ($lang === 'ja') {
                $config['default_font'] = $fontConfig['default'];
            }
        }

        return new \Mpdf\Mpdf($config);
    }

    private function findCjkFont(): ?array
    {
        $customDir  = storage_path('fonts');
        $customFile = $customDir . '/NotoSansJP-Regular.ttf';
        if (file_exists($customFile)) {
            return [
                'name'    => 'NotoSansJP-Regular.ttf',
                'dir'     => [$customDir],
                'data'    => ['notosansjp' => ['R' => 'NotoSansJP-Regular.ttf']],
                'default' => 'notosansjp',
            ];
        }

        $sysRoot  = rtrim((string) (getenv('SystemRoot') ?: 'C:/Windows'), '/\\');
        $winFonts = $sysRoot . '/Fonts';

        $candidates = [
            'YuGothR.ttc'  => ['fontName' => 'yugothic', 'ttcId' => 1],
            'meiryo.ttc'   => ['fontName' => 'meiryo',   'ttcId' => 1],
            'msgothic.ttc' => ['fontName' => 'msgothic', 'ttcId' => 1],
        ];

        foreach ($candidates as $file => $meta) {
            if (file_exists($winFonts . '/' . $file)) {
                return [
                    'name'    => $file,
                    'dir'     => [$winFonts],
                    'data'    => [
                        $meta['fontName'] => [
                            'R'         => $file,
                            'TTCfontID' => ['R' => $meta['ttcId']],
                        ],
                    ],
                    'default' => $meta['fontName'],
                ];
            }
        }

        return null;
    }

    private function buildCoverPage(array $guide): string
    {
        $title    = htmlspecialchars($guide['title']);
        $subtitle = htmlspecialchars($guide['subtitle']);
        $version  = htmlspecialchars($guide['version']);
        $year     = date('Y');

        return <<<HTML
        <div class="cover">
            <div class="cover-logo">⏱ Kintai</div>
            <div class="cover-title">{$title}</div>
            <div class="cover-subtitle">{$subtitle}</div>
            <div class="cover-version">{$version}</div>
            <div class="cover-year">{$year}</div>
        </div>
        <pagebreak/>
        HTML;
    }

    /**
     * @return array<string, array{title: string, sections: array<string, string>}>
     */
    private static function labels(): array
    {
        return [
            'fr' => ['title' => 'Table des matières', 'sections' => [
                'Quick-Start.md'      => 'Démarrage rapide',
                'Guide-Owner-FR.md'   => 'Guide Propriétaire',
                'Guide-Manager-FR.md' => 'Guide Responsable',
                'Guide-Staff-FR.md'   => 'Guide Personnel',
                'FAQ.md'              => 'Questions fréquentes (FAQ)',
                'Glossary.md'         => 'Glossaire',
                'Site-Map.md'         => 'Plan du site',
            ]],
            'en' => ['title' => 'Table of Contents', 'sections' => [
                'Quick-Start-EN.md'    => 'Quick Start',
                'Guide-Owner-EN.md'    => 'Owner Guide',
                'Guide-Manager-EN.md'  => 'Manager Guide',
                'Guide-Staff-EN.md'    => 'Staff Guide',
                'FAQ-EN.md'            => 'Frequently Asked Questions (FAQ)',
                'Glossary-EN.md'       => 'Glossary',
                'Site-Map-EN.md'       => 'Site Map',
            ]],
            'ja' => ['title' => '目次', 'sections' => [
                'Quick-Start-JA.md'    => 'クイックスタート',
                'Guide-Owner-JA.md'    => 'オーナーガイド',
                'Guide-Manager-JA.md'  => 'マネージャーガイド',
                'Guide-Staff-JA.md'    => 'スタッフガイド',
                'FAQ-JA.md'            => 'よくある質問 (FAQ)',
                'Glossary-JA.md'       => '用語集',
                'Site-Map-JA.md'       => 'サイトマップ',
            ]],
        ];
    }

    private function buildToc(array $guide): string
    {
        $lang = $guide['lang_tag'];

        $meta  = self::labels()[$lang] ?? self::labels()['en'];
        $items = '';
        $i     = 1;

        foreach ($guide['files'] as $idx => $file) {
            $name   = $meta['sections'][$file] ?? basename($file, '.md');
            $anchor = 'section-' . $idx;
            $items .= "<li><span class=\"toc-num\">{$i}</span> <a class=\"toc-link\" href=\"#{$anchor}\">{$name}</a></li>\n";
            $i++;
        }

        return <<<HTML
        <h1 class="toc-heading">{$meta['title']}</h1>
        <ol class="toc-list">
        {$items}
        </ol>
        <pagebreak/>
        HTML;
    }

    private function buildFullHtml(string $body, array $guide): string
    {
        $lang = $guide['lang_tag'];

        return <<<HTML
        <!DOCTYPE html>
        <html lang="{$lang}">
        <head>
            <meta charset="UTF-8">
            <title>{$guide['title']}</title>
        </head>
        <body>
        {$body}
        </body>
        </html>
        HTML;
    }

    private function buildFooter(array $guide): string
    {
        $title = htmlspecialchars($guide['title']);

        return <<<HTML
        <table width="100%" style="font-size:8pt; color:#888; border-top:1px solid #ddd; padding-top:4px;">
            <tr>
                <td width="70%">{$title} — {$guide['version']}</td>
                <td width="30%" align="right">{PAGENO} / {nbpg}</td>
            </tr>
        </table>
        HTML;
    }

    private function buildCss(string $lang): string
    {
        $fontFamily = $lang === 'ja'
            ? "notosansjp, yugothic, meiryo, msgothic, sans-serif"
            : "DejaVuSans, Arial, sans-serif";

        $codeFontFamily = "DejaVuSansMono, 'Courier New', monospace";

        return <<<CSS
        /* ── Reset ── */
        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: {$fontFamily};
            font-size: 10pt;
            line-height: 1.65;
            color: #2d2d2d;
        }

        /* ── Couverture ── */
        .cover {
            display: block;
            text-align: center;
            padding-top: 100px;
        }
        .cover-logo {
            font-size: 36pt;
            font-weight: bold;
            color: #1a3a5c;
            margin-bottom: 60px;
            letter-spacing: 2px;
        }
        .cover-title {
            font-size: 22pt;
            font-weight: bold;
            color: #1a3a5c;
            margin-bottom: 20px;
        }
        .cover-subtitle {
            font-size: 13pt;
            color: #4a6585;
            margin-bottom: 40px;
        }
        .cover-version {
            font-size: 10pt;
            color: #888;
            margin-bottom: 8px;
        }
        .cover-year {
            font-size: 10pt;
            color: #aaa;
        }

        /* ── Table des matières ── */
        .toc-heading {
            font-size: 18pt;
            color: #1a3a5c;
            border-bottom: 2px solid #1a3a5c;
            padding-bottom: 8px;
            margin-bottom: 20px;
        }
        .toc-list {
            list-style: none;
            padding: 0;
        }
        .toc-list li {
            font-size: 11pt;
            padding: 6px 0;
            border-bottom: 1px dotted #ccc;
            color: #333;
        }
        .toc-num {
            display: inline-block;
            width: 24px;
            color: #1a3a5c;
            font-weight: bold;
        }

        /* ── Titres ── */
        h1 {
            font-size: 20pt;
            color: #1a3a5c;
            border-bottom: 2px solid #1a3a5c;
            padding-bottom: 8px;
            margin-top: 16px;
            margin-bottom: 14px;
        }
        h2 {
            font-size: 14pt;
            color: #1a3a5c;
            border-bottom: 1px solid #b8ccde;
            padding-bottom: 4px;
            margin-top: 18px;
            margin-bottom: 10px;
        }
        h3 {
            font-size: 11pt;
            color: #2a5080;
            margin-top: 14px;
            margin-bottom: 6px;
        }
        h4 {
            font-size: 10pt;
            color: #2a5080;
            margin-top: 10px;
            margin-bottom: 4px;
        }

        /* ── Paragraphes ── */
        p {
            margin-bottom: 8px;
        }

        /* ── Listes ── */
        ul, ol {
            margin-left: 18px;
            margin-bottom: 8px;
        }
        li {
            margin-bottom: 3px;
        }

        /* ── Liens ── */
        a {
            color: #1a6eb5;
            text-decoration: underline;
        }
        a.toc-link {
            color: #1a3a5c;
            text-decoration: none;
            font-weight: bold;
        }

        /* ── Code ── */
        code {
            font-family: {$codeFontFamily};
            font-size: 8.5pt;
            background: #f4f6f8;
            color: #c7254e;
            padding: 1px 4px;
            border-radius: 3px;
        }
        pre {
            font-family: {$codeFontFamily};
            font-size: 8pt;
            background: #f4f6f8;
            border: 1px solid #dde3e9;
            border-left: 4px solid #1a6eb5;
            padding: 10px 12px;
            margin: 8px 0 12px;
            white-space: pre-wrap;
            word-wrap: break-word;
            line-height: 1.5;
            color: #2d2d2d;
        }
        pre code {
            background: none;
            color: inherit;
            padding: 0;
            font-size: inherit;
        }

        /* ── Blockquotes / callouts ── */
        blockquote {
            border-left: 4px solid #b8ccde;
            background: #f0f5fa;
            padding: 8px 12px;
            margin: 8px 0 12px;
            color: #4a5568;
            font-size: 9.5pt;
        }
        blockquote p {
            margin: 0;
        }

        /* ── Tableaux ── */
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 10px 0 14px;
            font-size: 9pt;
        }
        th {
            background: #1a3a5c;
            color: #fff;
            font-weight: bold;
            padding: 6px 8px;
            text-align: left;
            border: 1px solid #1a3a5c;
        }
        td {
            padding: 5px 8px;
            border: 1px solid #c8d8e8;
            vertical-align: top;
        }
        tr:nth-child(even) td {
            background: #f5f8fb;
        }

        /* ── Ligne horizontale ── */
        hr {
            border: none;
            border-top: 1px solid #dde3e9;
            margin: 14px 0;
        }

        /* ── Saut de section ── */
        .section-break {
            page-break-before: always;
        }
        CSS;
    }
}
