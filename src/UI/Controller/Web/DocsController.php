<?php

declare(strict_types=1);

namespace kintai\UI\Controller\Web;

use kintai\Core\Request;
use kintai\Core\Response;
use kintai\Core\Services\TranslationService;
use kintai\Core\Services\WikiPdfGeneratorService;
use kintai\UI\ViewRenderer;

final class DocsController
{
    public function __construct(
        private readonly ViewRenderer $view,
        private readonly TranslationService $translations,
    ) {}

    public function index(Request $request): Response
    {
        $locale = $this->translations->getLocale();
        $pdfs   = [];

        foreach (WikiPdfGeneratorService::supportedLangs() as $lang) {
            $filename    = 'Kintai-Guide-' . strtoupper($lang) . '.pdf';
            $path        = BASE_PATH . '/public/pdf/' . $filename;
            $exists      = file_exists($path);
            $pdfs[$lang] = [
                'filename'  => $filename,
                'available' => $exists,
                'size_kb'   => $exists ? (int) round(filesize($path) / 1024) : 0,
            ];
        }

        return Response::html($this->view->render('docs.index', [
            'pdfs'         => $pdfs,
            'locale'       => $locale,
            'public_token' => WikiPdfController::computeToken(),
        ], 'layout.app'));
    }
}
