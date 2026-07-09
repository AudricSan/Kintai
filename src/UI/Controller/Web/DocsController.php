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
        $firstPage = $this->wikiContent->firstPage();
        $languages = [];

        foreach (WikiContentService::supportedLangs() as $lang) {
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

        $flatItems = [];
        foreach ($toc as $group) {
            foreach ($group['items'] as $item) {
                $flatItems[] = $item;
            }
        }

        $currentIndex = null;
        foreach ($flatItems as $i => $item) {
            if ($item['slug'] === $page) {
                $currentIndex = $i;
                break;
            }
        }

        $otherLanguages = [];
        foreach (WikiContentService::supportedLangs() as $l) {
            if ($l === $lang) {
                continue;
            }
            $otherLanguages[$l] = $this->wikiContent->pageExists($l, $page);
        }

        return Response::html($this->view->render('docs.show', [
            'lang'           => $lang,
            'page'           => $page,
            'toc'            => $toc,
            'flatItems'      => $flatItems,
            'currentIndex'   => $currentIndex,
            'contentHtml'    => $contentHtml,
            'otherLanguages' => $otherLanguages,
        ], 'layout.app'));
    }
}
