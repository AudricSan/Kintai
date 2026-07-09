<?php

declare(strict_types=1);

namespace kintai\UI\Controller\Web;

use kintai\Core\Request;
use kintai\Core\Response;
use kintai\Core\Services\TranslationService;
use kintai\Core\Services\WikiContentService;
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
        $locale    = $this->translations->getLocale();
        $languages = [];

        foreach (WikiContentService::supportedLangs() as $lang) {
            $firstPage = $this->wikiContent->firstPage($lang);

            $languages[$lang] = [
                'first_page' => $firstPage,
                'browsable'  => $firstPage !== null && $this->wikiContent->pageExists($lang, $firstPage),
            ];
        }

        return Response::html($this->view->render('docs.index', [
            'languages' => $languages,
            'locale'    => $locale,
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
        ], 'layout.app'));
    }
}
