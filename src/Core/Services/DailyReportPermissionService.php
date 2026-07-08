<?php

declare(strict_types=1);

namespace kintai\Core\Services;

/**
 * Contrôle d'accès pour les rapports journaliers.
 *
 * Les permissions sont configurables par store via la colonne JSON `daily_report_settings`.
 * Structure du JSON :
 * {
 *   "enabled": true,
 *   "can_submit_roles":   ["staff","manager","admin"],
 *   "can_validate_roles": ["manager","admin"],
 *   "mail_recipients":    ["email@example.com"],
 *   "auto_send_on_validate": false
 * }
 */
final class DailyReportPermissionService
{
    private const DEFAULTS = [
        'enabled'               => true,
        'can_create_user_ids'   => [],
        'can_submit_roles'      => ['staff', 'manager', 'admin'],
        'can_validate_roles'    => ['manager', 'admin'],
        'mail_recipients'       => [],
        'auto_send_on_validate' => false,
        'auto_validate_time'    => null,
        'reminder_time'         => '15:00',
        'no_report_time'        => '18:00',
        'show_columns'          => ['employee', 'type', 'schedule', 'gross_hours', 'pause', 'net_hours'],
        'cumulative_mode'       => 'per_day',
    ];

    public function getSettings(array $store): array
    {
        $raw = $store['daily_report_settings'] ?? null;
        if (!$raw) {
            return self::DEFAULTS;
        }
        $decoded = json_decode($raw, true);
        return array_merge(self::DEFAULTS, is_array($decoded) ? $decoded : []);
    }

    public function isEnabled(array $store): bool
    {
        return (bool) $this->getSettings($store)['enabled'];
    }

    /**
     * Vérifie si l'utilisateur peut créer un rapport pour ce store.
     * - Super admin : toujours autorisé.
     * - Admin/manager du store : toujours autorisé (si fonctionnalité activée).
     * - Staff : autorisé si dans la liste blanche `can_create_user_ids`
     *   (liste vide = tous les membres staff peuvent créer).
     */
    public function canCreate(array $user, array $store, ?array $membership): bool
    {
        if (!$this->isEnabled($store)) {
            return false;
        }
        if (!empty($user['is_admin'])) {
            return true;
        }
        if ($membership === null) {
            return false;
        }
        if (in_array($membership['role'], ['admin', 'manager'], true)) {
            return true;
        }
        $settings   = $this->getSettings($store);
        $allowedIds = array_map('intval', $settings['can_create_user_ids'] ?? []);
        if (empty($allowedIds)) {
            return true;
        }
        return in_array((int) $user['id'], $allowedIds, true);
    }

    /**
     * L'auteur peut modifier son propre brouillon.
     * Managers/admins peuvent modifier tout brouillon ou rapport soumis (avant validation finale).
     */
    public function canEdit(array $user, array $store, array $report, ?array $membership): bool
    {
        $status = $report['status'];
        if (!in_array($status, ['draft', 'submitted'], true)) {
            return false;
        }
        if (!empty($user['is_admin'])) {
            return true;
        }
        if ($membership === null) {
            return false;
        }
        if (in_array($membership['role'], ['admin', 'manager'], true)) {
            return true;
        }
        // Staff : uniquement son propre brouillon
        return $status === 'draft' && (int) $report['author_id'] === (int) $user['id'];
    }

    public function canSubmit(array $user, array $store, array $report, ?array $membership): bool
    {
        if ($report['status'] !== 'draft') {
            return false;
        }
        if (!empty($user['is_admin'])) {
            return true;
        }
        if ($membership === null) {
            return false;
        }
        $settings = $this->getSettings($store);
        return in_array($membership['role'], $settings['can_submit_roles'], true);
    }

    public function canValidate(array $user, array $store, array $report, ?array $membership): bool
    {
        if ($report['status'] !== 'submitted') {
            return false;
        }
        if (!empty($user['is_admin'])) {
            return true;
        }
        if ($membership === null) {
            return false;
        }
        $settings = $this->getSettings($store);
        return in_array($membership['role'], $settings['can_validate_roles'], true);
    }

    public function canSendMail(array $user, array $store, array $report, ?array $membership): bool
    {
        if ($report['status'] !== 'validated') {
            return false;
        }
        if (!empty($user['is_admin'])) {
            return true;
        }
        if ($membership === null) {
            return false;
        }
        return in_array($membership['role'], ['admin', 'manager'], true);
    }

    public function canDelete(array $user, array $store, array $report, ?array $membership): bool
    {
        if ($report['deleted_at'] !== null) {
            return false;
        }
        if (!empty($user['is_admin'])) {
            return true;
        }
        if ($membership === null) {
            return false;
        }
        return in_array($membership['role'], ['admin', 'manager'], true);
    }

    public function canViewList(array $user, array $store, ?array $membership): bool
    {
        if (!empty($user['is_admin'])) {
            return true;
        }
        if ($membership === null) {
            return false;
        }
        return $this->isEnabled($store);
    }

    public function canViewReport(array $user, array $store, array $report, ?array $membership): bool
    {
        if (!empty($user['is_admin'])) {
            return true;
        }
        if ($membership === null) {
            return false;
        }
        // Staff ne peut voir que ses propres rapports
        if ($membership['role'] === 'staff') {
            return (int) $report['author_id'] === (int) $user['id'];
        }
        return true;
    }
}
