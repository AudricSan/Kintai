<?php

declare(strict_types=1);

namespace kintai\UI\Controller\Web;

use kintai\Core\Request;
use kintai\Core\Response;
use kintai\Core\Services\TranslationService;
use kintai\Core\Services\WikiContentService;
use kintai\Core\Services\WikiPdfGeneratorService;
use kintai\UI\ViewRenderer;

final class DocsController
{
    public function __construct(
        private readonly ViewRenderer $view,
        private readonly TranslationService $translations,
        private readonly WikiContentService $wikiContent,
    ) {}

    public function index(Request $request): Response
    {
        $locale = $this->translations->getLocale();
        $pdfs   = [];

        foreach (WikiPdfGeneratorService::supportedLangs() as $lang) {
            $filename   = 'Kintai-Guide-' . strtoupper($lang) . '.pdf';
            $path       = BASE_PATH . '/public/pdf/' . $filename;
            $exists     = file_exists($path);
            $firstPage  = $this->wikiContent->firstPage($lang);

            $pdfs[$lang] = [
                'filename'   => $filename,
                'available'  => $exists,
                'size_kb'    => $exists ? (int) round(filesize($path) / 1024) : 0,
                'first_page' => $firstPage,
                'browsable'  => $firstPage !== null && $this->wikiContent->pageExists($lang, $firstPage),
            ];
        }

        return Response::html($this->view->render('docs.index', [
            'pdfs'         => $pdfs,
            'locale'       => $locale,
            'public_token' => WikiPdfController::computeToken(),
        ], 'layout.app'));
    }

    public function show(Request $request): Response
    {
        $lang = strtolower((string) $request->param('lang'));
        $page = (string) $request->param('page');

        $toc         = $this->wikiContent->tableOfContents($lang);
        $contentHtml = $this->wikiContent->render($lang, $page);

        $currentIndex = null;
        foreach ($toc as $i => $entry) {
            if ($entry['file'] === $page) {
                $currentIndex = $i;
                break;
            }
        }

        return Response::html($this->view->render('docs.show', [
            'lang'         => $lang,
            'page'         => $page,
            'toc'          => $toc,
            'currentIndex' => $currentIndex,
            'contentHtml'  => $contentHtml,
            'public_token' => WikiPdfController::computeToken(),
        ], 'layout.app'));
    }
}
