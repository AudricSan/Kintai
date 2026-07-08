<?php

declare(strict_types=1);

namespace kintai\UI\Controller\Web;

use kintai\Core\Exceptions\ForbiddenException;
use kintai\Core\Request;
use kintai\Core\Response;
use kintai\Core\Services\AuditLogger;
use kintai\Core\Services\DailyReportAutoValidateService;

/**
 * Endpoint HTTP pour les tâches planifiées OVH (« Tâches planifiées »).
 *
 * Protégé par un token secret : CRON_SECRET (variable d'environnement).
 *
 * ─── Configuration OVH ────────────────────────────────────────────────────────
 *
 *  1. Dans votre espace OVH → Hébergement → Tâches planifiées :
 *     - Commande : curl "https://votresite.com/cron/auto-validate?token=VOTRE_SECRET"
 *     - Fréquence : Toutes les heures
 *
 *  2. Sur un VPS/dédié (crontab Linux) :
 *     0 * * * * curl -s "https://votresite.com/cron/auto-validate?token=VOTRE_SECRET" >> /var/log/kintai-cron.log
 *     # Ou via CLI PHP :
 *     0 * * * * php /var/www/html/scripts/auto-validate-reports.php >> /var/log/kintai-cron.log 2>&1
 *
 *  3. Variable d'environnement à définir sur le serveur :
 *     CRON_SECRET=une_chaine_aleatoire_longue_et_secrete
 */
final class CronController
{
    public function __construct(
        private readonly DailyReportAutoValidateService $autoValidate,
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * GET /cron/auto-validate?token=SECRET[&date=YYYY-MM-DD][&all=1]
     *
     * - token : obligatoire, doit correspondre à CRON_SECRET
     * - date  : optionnel, YYYY-MM-DD (défaut : hier)
     * - all   : optionnel, "1" pour traiter tous les stores sans filtre horaire
     */
    public function autoValidate(Request $request): Response
    {
        $secret = (string) env('CRON_SECRET', '');
        if ($secret === '' || $request->query('token', '') !== $secret) {
            throw new ForbiddenException('Token cron invalide ou CRON_SECRET non configuré.');
        }

        set_time_limit(0);

        $rawDate    = $request->query('date', '');
        $targetDate = preg_match('/^\d{4}-\d{2}-\d{2}$/', $rawDate)
            ? $rawDate
            : date('Y-m-d', strtotime('yesterday'));

        $all         = $request->query('all', '0') === '1';
        $currentHour = $all ? null : date('H');

        $result = $this->autoValidate->run($targetDate, $currentHour);

        $this->auditLogger->log($request, 'cron.auto_validated', 'system', null, [
            'date'      => $targetDate,
            'validated' => $result['validated'],
            'skipped'   => $result['skipped'],
            'errors'    => $result['errors'] ?? 0,
        ]);

        return Response::json([
            'ok'        => true,
            'date'      => $targetDate,
            'validated' => $result['validated'],
            'skipped'   => $result['skipped'],
            'errors'    => $result['errors'],
        ]);
    }
}
