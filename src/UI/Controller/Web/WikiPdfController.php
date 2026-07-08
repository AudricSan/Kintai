<?php

declare(strict_types=1);

namespace kintai\UI\Controller\Web;

use kintai\Core\Exceptions\ForbiddenException;
use kintai\Core\Request;
use kintai\Core\Response;
use kintai\Core\Services\WikiPdfGeneratorService;

/**
 * Endpoint public de génération des guides PDF.
 *
 * Protégé par un token dérivé du fichier storage/installed.lock :
 *   token = SHA256('kintai-pdf:' + contenu_lock)
 *
 * ─── Exemples d'utilisation ──────────────────────────────────────────────────
 *
 *  Générer tous les PDFs :
 *    GET /api/v1/pdf/generate?token=<token>
 *
 *  Générer un seul PDF :
 *    GET /api/v1/pdf/generate?token=<token>&lang=fr
 *
 *  Automatisation (cron, CI/CD) :
 *    curl "https://votresite.com/api/v1/pdf/generate?token=<token>"
 *
 * Les PDFs générés sont servis directement par Apache depuis public/pdf/.
 */
final class WikiPdfController
{
    public function __construct(
        private readonly WikiPdfGeneratorService $pdfService,
    ) {}

    /**
     * GET /api/v1/pdf/generate?token=<token>[&lang=fr|en|ja]
     */
    public function generate(Request $request): Response
    {
        $this->requireValidToken($request);

        $lang      = strtolower($request->query('lang', ''));
        $supported = WikiPdfGeneratorService::supportedLangs();

        if ($lang !== '' && !in_array($lang, $supported, true)) {
            return Response::json([
                'ok'    => false,
                'error' => 'Paramètre lang invalide. Valeurs acceptées : ' . implode(', ', $supported) . '.',
            ], 422);
        }

        set_time_limit(300);

        $start     = microtime(true);
        $rawResult = ($lang !== '')
            ? [$lang => $this->pdfService->generate($lang)]
            : $this->pdfService->generateAll();

        $ms        = (int) round((microtime(true) - $start) * 1000);
        $generated = [];
        $errors    = [];
        $allOk     = true;

        foreach ($rawResult as $l => $res) {
            if ($res['ok']) {
                $generated[$l] = $res['size_kb'] . ' Ko';
            } else {
                $allOk    = false;
                $errors[] = strtoupper($l) . ' : ' . $res['error'];
            }
        }

        return Response::json([
            'ok'          => $allOk,
            'lang'        => $lang ?: 'all',
            'generated'   => $generated,
            'duration_ms' => $ms,
            'error'       => $errors !== [] ? implode(' | ', $errors) : null,
        ], $allOk ? 200 : 500);
    }

    // -------------------------------------------------------------------------
    // Token
    // -------------------------------------------------------------------------

    public static function computeToken(string $lockPath = ''): string
    {
        $lockPath = $lockPath !== '' ? $lockPath : storage_path('installed.lock');

        if (!file_exists($lockPath)) {
            return '';
        }

        $content = trim((string) file_get_contents($lockPath));

        if ($content === '') {
            return '';
        }

        return hash('sha256', 'kintai-pdf:' . $content);
    }

    private function requireValidToken(Request $request): void
    {
        $expected = self::computeToken();

        if ($expected === '') {
            throw new ForbiddenException('Installation non initialisée.');
        }

        $provided = (string) $request->query('token', '');

        if ($provided === '' || !hash_equals($expected, $provided)) {
            throw new ForbiddenException('Token invalide.');
        }
    }
}
