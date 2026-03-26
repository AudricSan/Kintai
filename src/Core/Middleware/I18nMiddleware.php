<?php

declare(strict_types=1);

namespace kintai\Core\Middleware;

use Closure;
use kintai\Core\Request;
use kintai\Core\Response;
use kintai\Core\Services\TranslationService;

final class I18nMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly TranslationService $translationService
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $locale = $this->determineLocale($request);
        $this->translationService->setLocale($locale);

        return $next($request);
    }

    private function determineLocale(Request $request): string
    {
        // 1. Session preferred language
        if (isset($_SESSION['locale'])) {
            return $_SESSION['locale'];
        }

        // 2. User profile language
        $user = $request->getAttribute('auth_user');
        if ($user && !empty($user['language'])) {
            return $user['language'];
        }

        // 3. Browser language
        $acceptLang = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '';
        if ($acceptLang) {
            $langs = explode(',', $acceptLang);
            $primary = strtolower(substr($langs[0], 0, 2));
            if (in_array($primary, ['en', 'fr', 'ja'], true)) {
                return $primary;
            }
        }

        // 4. Default config
        return 'fr';
    }
}
