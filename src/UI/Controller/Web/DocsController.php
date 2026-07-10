<?php

declare(strict_types=1);

namespace kintai\UI\Controller\Web;

use kintai\Core\Exceptions\ForbiddenException;
use kintai\Core\Request;
use kintai\Core\Response;
use kintai\Core\Services\TranslationService;
use kintai\Core\Services\WikiContentService;
use kintai\Core\Services\WikiSyncService;
use kintai\UI\ViewRenderer;

final class DocsController
{
    public function __construct(
        private readonly ViewRenderer $view,
        private readonly TranslationService $translations,
        private readonly WikiContentService $wikiContent,
        private readonly WikiSyncService $wikiSync,
    ) {}

    public function index(Request $request): Response
    {
        $locale    = $this->translations->getLocale();
        $firstPage = $this->wikiContent->firstPage();
        $languages = [];

        foreach (WikiContentService::supportedLangs() as $lang) {
            $languages[$lang] = [
                'first_page'      => $firstPage,
                'browsable'       => $firstPage !== null && $this->wikiContent->pageExists($lang, $firstPage),
                'github_wiki_url' => $this->wikiContent->githubWikiUrl($lang, $firstPage),
            ];
        }

        $user = $request->getAttribute('auth_user');

        return Response::html($this->view->render('docs.index', [
            'languages'      => $languages,
            'locale'         => $locale,
            'wikiConfigured' => $this->wikiContent->isConfigured(),
            'isOwner'        => !empty($user['is_admin']),
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
            'githubWikiUrl'  => $this->wikiContent->githubWikiUrl($lang, $page),
        ], 'layout.app'));
    }

    /**
     * POST /docs/sync — clone (si absent) ou met à jour (si déjà présent) le
     * wiki local `.wiki/` depuis le wiki GitHub. Réservé au propriétaire.
     */
    public function sync(Request $request): Response
    {
        $this->requireOwner($request);

        $result = $this->wikiSync->sync();

        return Response::json($result, $result['ok'] ? 200 : 500);
    }

    private function requireOwner(Request $request): void
    {
        $user = $request->getAttribute('auth_user');
        if (empty($user['is_admin'])) {
            throw new ForbiddenException('Réservé au propriétaire.');
        }
    }
}
