<?php

declare(strict_types=1);

use kintai\Core\Container;
use kintai\Core\Services\TranslationService;

/**
 * Formate un montant selon la devise du store.
 *
 * JPY → pas de décimales, séparateur milliers ".", symbole "円" en suffixe
 * EUR → 2 décimales, séparateur décimal ",", milliers ".", symbole "€" en suffixe
 * USD → 2 décimales, séparateur décimal ".", milliers ",", symbole "$" en préfixe
 * Autres → 2 décimales, code ISO en suffixe
 */
function format_currency(float $amount, string $currency = 'EUR'): string
{
    $currency = strtoupper(trim($currency));

    return match ($currency) {
        'JPY'   => number_format($amount, 0, '.', '.') . '円',
        'KRW'   => number_format($amount, 0, '.', '.') . '₩',
        'EUR'   => number_format($amount, 2, ',', '.') . ' €',
        'USD'   => '$' . number_format($amount, 2, '.', ','),
        'GBP'   => '£' . number_format($amount, 2, '.', ','),
        'CHF'   => 'CHF ' . number_format($amount, 2, '.', '\''),
        default => number_format($amount, 2, '.', ',') . ' ' . $currency,
    };
}

/**
 * Traduit une clé donnée.
 */
function __(string $key, array $replace = []): string
{
    try {
        $container = Container::getInstance();
        if ($container->has(TranslationService::class)) {
            $translationService = $container->make(TranslationService::class);
            return $translationService->translate($key, $replace);
        }
    } catch (\Throwable $e) {
        // En cas d'erreur avant que le service soit prêt
    }
    
    return $key;
}
