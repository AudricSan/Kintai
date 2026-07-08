<?php

declare(strict_types=1);

namespace kintai\UI\Controller\Web;

use kintai\Core\Auth\AuthService;
use kintai\Core\Exceptions\NotFoundException;
use kintai\Core\Repositories\IcalTokenRepositoryInterface;
use kintai\Core\Repositories\ShiftRepositoryInterface;
use kintai\Core\Repositories\ShiftTypeRepositoryInterface;
use kintai\Core\Repositories\StoreRepositoryInterface;
use kintai\Core\Repositories\TimeoffRequestRepositoryInterface;
use kintai\Core\Repositories\UserRepositoryInterface;
use kintai\Core\Request;
use kintai\Core\Response;
use kintai\Core\Services\AuditLogger;
use kintai\Core\Services\IcalService;
use kintai\UI\Controller\Web\HasBaseUrl;

final class IcalController
{
    use HasBaseUrl;
    public function __construct(
        private readonly IcalService                        $ical,
        private readonly IcalTokenRepositoryInterface       $icalTokens,
        private readonly ShiftRepositoryInterface           $shifts,
        private readonly ShiftTypeRepositoryInterface       $shiftTypes,
        private readonly StoreRepositoryInterface           $stores,
        private readonly TimeoffRequestRepositoryInterface  $timeoffRequests,
        private readonly UserRepositoryInterface            $users,
        private readonly AuthService                        $auth,
        private readonly AuditLogger                        $auditLogger,
    ) {}

    /**
     * GET /ical/{token}/{store_id}/shifts.ics
     * Route publique protégée par token.
     */
    public function feed(Request $request): Response
    {
        $token   = $request->param('token') ?? '';
        $storeId = (int) ($request->param('store_id') ?? 0);

        $tokenRow = $this->icalTokens->findByToken($token);
        if (!$tokenRow || (int) $tokenRow['store_id'] !== $storeId) {
            throw new NotFoundException('Flux iCal introuvable.');
        }

        $userId = (int) $tokenRow['user_id'];
        $store  = $this->stores->findById($storeId);
        $user   = $this->users->findById($userId);

        if (!$store || !$user) {
            throw new NotFoundException('Ressource introuvable.');
        }

        $cutoff = date('Y-m-d H:i:s', strtotime('-30 days'));
        $shifts = array_values(array_filter(
            $this->shifts->findByUser($userId),
            fn($s) => (int) ($s['store_id'] ?? 0) === $storeId
                && (empty($s['deleted_at']) || ($s['deleted_at'] ?? '') >= $cutoff)
        ));

        $timeoffs = array_values(array_filter(
            $this->timeoffRequests->findByUser($userId),
            fn($t) => (int) ($t['store_id'] ?? 0) === $storeId && ($t['status'] ?? '') === 'approved'
        ));

        $typesMap = [];
        foreach ($this->shiftTypes->findAll() as $t) {
            $typesMap[(int) $t['id']] = $t;
        }

        $content  = $this->ical->build($shifts, $timeoffs, $store, $user, $typesMap);
        $filename = 'kintai-' . ($store['code'] ?? $storeId) . '-shifts.ics';

        $this->auditLogger->log($request, 'export.ical', 'ical_token', (int) $tokenRow['id'], [
            'store_id' => $storeId,
            'format'   => 'ical',
        ], $storeId);

        return Response::ical($content, $filename);
    }

    /**
     * POST /ical/{store_id}/regenerate
     * Régénère le token pour l'employé connecté + ce store.
     */
    public function regenerate(Request $request): Response
    {
        $user = $this->auth->user();
        if (!$user) {
            return Response::redirect($this->base() . '/login');
        }

        $userId  = (int) $user['id'];
        $storeId = (int) ($request->param('store_id') ?? 0);

        $this->icalTokens->deleteByUserAndStore($userId, $storeId);
        $saved = $this->icalTokens->save([
            'user_id'    => $userId,
            'store_id'   => $storeId,
            'token'      => bin2hex(random_bytes(32)),
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        $this->auditLogger->log($request, 'ical_token.regenerated', 'ical_token', (int) ($saved['id'] ?? 0), [
            'store_id' => $storeId,
        ], $storeId, $userId);

        return Response::redirect($this->base() . '/profile?success=1');
    }


}
