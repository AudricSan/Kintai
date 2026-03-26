<?php

declare(strict_types=1);

namespace kintai\Core\Services;

use kintai\Core\Repositories\AuditLogRepositoryInterface;
use kintai\Core\Request;

/**
 * Service de journalisation des actions utilisateur.
 * S'appuie sur la table audit_log. Ne propage jamais d'exception.
 */
final class AuditLogger
{
    public function __construct(
        private readonly AuditLogRepositoryInterface $repository,
    ) {}

    /**
     * Enregistre une action dans le journal.
     *
     * @param Request     $request       Requête courante (pour IP, user agent, auth_user)
     * @param string      $action        Ex : 'shift.created', 'auth.login'
     * @param string      $resourceType  Ex : 'shift', 'user', 'store'
     * @param int|null    $resourceId    ID de la ressource concernée
     * @param array       $details       Données contextuelles sérialisées en JSON
     * @param int|null    $storeId       Store concerné (si connu)
     * @param int|null    $userId        Override de l'utilisateur (utile pour login)
     */
    public function log(
        Request $request,
        string $action,
        string $resourceType,
        ?int $resourceId = null,
        array $details = [],
        ?int $storeId = null,
        ?int $userId = null,
    ): void {
        try {
            // Récupérer l'utilisateur depuis les attributs de la requête si non fourni
            if ($userId === null) {
                $authUser = $request->getAttribute('auth_user');
                $userId   = $authUser ? ((int) ($authUser['id'] ?? 0) ?: null) : null;
            }

            $this->repository->log([
                'store_id'      => $storeId,
                'user_id'       => $userId,
                'action'        => $action,
                'resource_type' => $resourceType,
                'resource_id'   => $resourceId,
                'details'       => $details ? json_encode($details, JSON_UNESCAPED_UNICODE) : null,
                'ip_address'    => $this->resolveIp(),
                'user_agent'    => isset($_SERVER['HTTP_USER_AGENT'])
                    ? mb_substr($_SERVER['HTTP_USER_AGENT'], 0, 500)
                    : null,
                'created_at'    => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable) {
            // Le logging ne doit jamais faire planter l'application
        }
    }

    private function resolveIp(): string
    {
        foreach (['HTTP_X_FORWARDED_FOR', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'] as $key) {
            if (!empty($_SERVER[$key])) {
                return trim(explode(',', $_SERVER[$key])[0]);
            }
        }
        return '0.0.0.0';
    }
}
