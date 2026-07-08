<?php

declare(strict_types=1);

namespace kintai\Core\Middleware;

use Closure;
use kintai\Core\Repositories\LanguageRepositoryInterface;
use kintai\Core\Request;
use kintai\Core\Response;
use kintai\Core\Services\TranslationService;

final class I18nMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly TranslationService $translationService,
        private readonly LanguageRepositoryInterface $languageRepository,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $locale = $this->determineLocale($request);
        $this->translationService->setLocale($locale);

        return $next($request);
    }

    private function determineLocale(Request $request): string
    {
        $activeCodes = array_column($this->languageRepository->findAllActive(), 'code');
        if ($activeCodes === []) {
            return 'en';
        }

        // 1. Session preferred language
        if (isset($_SESSION['locale']) && in_array($_SESSION['locale'], $activeCodes, true)) {
            return $_SESSION['locale'];
        }

        // 2. User profile language
        $user = $request->getAttribute('auth_user');
        if ($user && !empty($user['language']) && in_array($user['language'], $activeCodes, true)) {
            return $user['language'];
        }

        // 3. Browser language
        $acceptLang = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '';
        if ($acceptLang) {
            $langs = explode(',', $acceptLang);
            $primary = strtolower(substr($langs[0], 0, 2));
            if (in_array($primary, $activeCodes, true)) {
                return $primary;
            }
        }

        // 4. Langue par défaut configurée (fallback ultime : première langue active)
        $default = $this->languageRepository->findDefault();
        return $default['code'] ?? $activeCodes[0];
    }
}
