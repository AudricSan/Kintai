<?php

declare(strict_types=1);

namespace kintai\Core\Services;

use kintai\Core\Mail\MailerService;

/**
 * Envoi du rapport journalier par e-mail.
 * La locale est résolue dans cet ordre : override explicite → langue de l'auteur → locale du store → 'fr'.
 */
final class DailyReportMailService
{
    public function __construct(
        private readonly DailyReportPermissionService $permissions,
        private readonly MailerService $mailer,
        private readonly TranslationService $translations,
    ) {}

    /**
     * @param string|null $pdfBytes  Octets bruts du PDF (null = pas de pièce jointe)
     * @param array       $author    Données de l'auteur (pour déduire la langue)
     * @param string|null $locale    Locale explicite ('fr', 'en', 'ja') — prioritaire sur l'auteur/store
     */
    public function send(
        array   $report,
        array   $store,
        ?string $pdfBytes = null,
        array   $author   = [],
        ?string $locale   = null,
    ): bool {
        $settings   = $this->permissions->getSettings($store);
        $recipients = array_values(array_filter(
            $settings['mail_recipients'] ?? [],
            fn($e) => filter_var($e, FILTER_VALIDATE_EMAIL) !== false,
        ));

        if (empty($recipients)) {
            return false;
        }

        $resolved = $this->resolveLocale($author, $store, $locale);
        $previous = $this->translations->getLocale();
        $this->translations->setLocale($resolved);

        try {
            $storeName  = $store['name'] ?? 'Kintai';
            $reportDate = $report['report_date'] ?? '';
            $subject    = '[' . $storeName . '] ' . __('dr_title') . ' — ' . $reportDate;
            $body       = $this->buildBody($report, $store, $resolved);
        } finally {
            $this->translations->setLocale($previous);
        }

        $tmpFile     = null;
        $attachments = [];

        if ($pdfBytes !== null) {
            $tmpFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'kintai_dr_' . bin2hex(random_bytes(8)) . '.pdf';
            file_put_contents($tmpFile, $pdfBytes);
            $attachments = [$tmpFile];
        }

        try {
            return $this->mailer->send($recipients, $subject, $body, $attachments);
        } finally {
            if ($tmpFile !== null && file_exists($tmpFile)) {
                unlink($tmpFile);
            }
        }
    }

    private function resolveLocale(array $author, array $store, ?string $override = null): string
    {
        $raw  = $override ?? $author['language'] ?? $store['locale'] ?? 'fr';
        $lang = strtolower(substr((string) $raw, 0, 2));
        return match ($lang) {
            'ja'    => 'ja',
            'en'    => 'en',
            default => 'fr',
        };
    }

    private function buildBody(array $report, array $store, string $locale): string
    {
        $storeName = htmlspecialchars($store['name'] ?? '');
        $date      = htmlspecialchars($report['report_date'] ?? '');
        $sales     = number_format((float) ($report['sales_total']    ?? 0), 0, ',', ' ');
        $customers = (int) ($report['customer_count'] ?? 0);
        $labor     = number_format((float) ($report['labor_cost']     ?? 0), 0, ',', ' ');
        $waste     = number_format((float) ($report['waste_total']    ?? 0), 0, ',', ' ');
        $notes     = nl2br(htmlspecialchars($report['notes'] ?? '—'));
        $currency  = htmlspecialchars($store['currency'] ?? '¥');

        // Labels traduits (locale déjà appliquée par send() avant cet appel)
        $lTitle     = htmlspecialchars(__('dr_title'));
        $lDate      = htmlspecialchars(__('date'));
        $lSales     = htmlspecialchars(__('dr_sales'));
        $lCustomers = htmlspecialchars(__('dr_customers'));
        $lLabor     = htmlspecialchars(__('dr_labor'));
        $lWaste     = htmlspecialchars(__('dr_waste'));
        $lNotes     = htmlspecialchars(__('dr_notes'));

        return <<<HTML
        <!DOCTYPE html>
        <html lang="{$locale}">
        <head><meta charset="UTF-8"><title>{$lTitle}</title></head>
        <body style="font-family:Arial,sans-serif;color:#222;max-width:600px;margin:auto;">
          <h2 style="color:#2c3e50;border-bottom:2px solid #2c3e50;padding-bottom:8px;">
            {$lTitle} — {$storeName}
          </h2>
          <p style="color:#666;">{$lDate} : <strong>{$date}</strong></p>
          <table style="width:100%;border-collapse:collapse;margin-top:16px;">
            <tr style="background:#eaf2fb;">
              <td style="padding:8px 12px;font-weight:bold;">{$lSales}</td>
              <td style="padding:8px 12px;text-align:right;">{$sales} {$currency}</td>
            </tr>
            <tr>
              <td style="padding:8px 12px;font-weight:bold;">{$lCustomers}</td>
              <td style="padding:8px 12px;text-align:right;">{$customers}</td>
            </tr>
            <tr style="background:#eaf2fb;">
              <td style="padding:8px 12px;font-weight:bold;">{$lLabor}</td>
              <td style="padding:8px 12px;text-align:right;">{$labor} {$currency}</td>
            </tr>
            <tr>
              <td style="padding:8px 12px;font-weight:bold;">{$lWaste}</td>
              <td style="padding:8px 12px;text-align:right;">{$waste} {$currency}</td>
            </tr>
            <tr style="background:#eaf2fb;">
              <td style="padding:8px 12px;font-weight:bold;">{$lNotes}</td>
              <td style="padding:8px 12px;">{$notes}</td>
            </tr>
          </table>
          <p style="margin-top:24px;font-size:11px;color:#999;">
            Kintai — {$storeName}
          </p>
        </body>
        </html>
        HTML;
    }
}
