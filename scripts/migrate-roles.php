<?php

/**
 * Backfill des rôles dynamiques (roles/role_assignments) à partir des rôles
 * historiques (users.is_admin, store_user.role). Phase 2 de la refonte RBAC
 * — voir task/mermission.md. Ne modifie ni users.is_admin ni store_user.role
 * (l'app continue de lire ces colonnes tant que la Phase 3 n'est pas faite) ;
 * ce script ne fait qu'ajouter les lignes role_assignments correspondantes.
 *
 * Usage:
 *   php scripts/migrate-roles.php --dry-run   # rapport de comparaison, aucune écriture
 *   php scripts/migrate-roles.php             # applique le backfill (idempotent, ré-exécutable)
 */

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));

require BASE_PATH . '/vendor/autoload.php';
require BASE_PATH . '/src/Core/helpers.php';

use kintai\Core\Application;
use kintai\Core\Repositories\UserRepositoryInterface;
use kintai\Core\Repositories\StoreRepositoryInterface;
use kintai\Core\Repositories\StoreUserRepositoryInterface;
use kintai\Core\Repositories\RoleRepositoryInterface;
use kintai\Core\Repositories\RoleAssignmentRepositoryInterface;

$dryRun = in_array('--dry-run', $argv, true);

$app = new Application(BASE_PATH);
$app->boot();
$container = $app->container();

$users        = $container->make(UserRepositoryInterface::class);
$stores       = $container->make(StoreRepositoryInterface::class);
$storeUsers   = $container->make(StoreUserRepositoryInterface::class);
$roles        = $container->make(RoleRepositoryInterface::class);
$assignments  = $container->make(RoleAssignmentRepositoryInterface::class);

$ownerRole    = $roles->findBySlug('owner');
$managerRole  = $roles->findBySlug('manager');
$employeeRole = $roles->findBySlug('employee');

if ($ownerRole === null || $managerRole === null || $employeeRole === null) {
    fwrite(STDERR, "Rôles par défaut introuvables (owner/manager/employee). Applique d'abord les migrations de la Phase 1 (php scripts/db-migrate.php).\n");
    exit(1);
}

/**
 * Rôle destination pour une valeur historique de store_user.role. Le rôle
 * 'admin' (store-level, l'ancien "Store Administrator") est fusionné dans
 * Manager — il a toujours eu exactement les mêmes permissions dans le code.
 */
function targetStoreRoleId(string $legacyRole, array $managerRole, array $employeeRole): int
{
    return in_array($legacyRole, ['admin', 'manager'], true) ? (int) $managerRole['id'] : (int) $employeeRole['id'];
}

// ── Construit la liste des affectations désirées ────────────────────────
// Chaque entrée : ['user_id' => int, 'role_id' => int, 'scope_type' => 'global'|'store', 'scope_id' => ?int]
$desired = [];

foreach ($users->findAll() as $user) {
    if (!empty($user['is_admin'])) {
        $desired[] = [
            'user_id'    => (int) $user['id'],
            'role_id'    => (int) $ownerRole['id'],
            'scope_type' => 'global',
            'scope_id'   => null,
        ];
    }
}

foreach ($stores->findAll() as $store) {
    $storeId = (int) $store['id'];
    foreach ($storeUsers->findByStore($storeId) as $membership) {
        $roleId = targetStoreRoleId((string) ($membership['role'] ?? 'staff'), $managerRole, $employeeRole);
        $desired[] = [
            'user_id'    => (int) $membership['user_id'],
            'role_id'    => $roleId,
            'scope_type' => 'store',
            'scope_id'   => $storeId,
        ];
    }
}

// ── Rapport de comparaison ancien calcul vs nouveau calcul, par utilisateur ──
// L'ancien calcul est celui d'AuthService::isAdmin()/managedStoreIds() tel
// qu'il se comporte aujourd'hui ; le nouveau est celui que managedStoreIds()
// rendra une fois branché sur role_assignments (Phase 3). Les deux doivent
// coïncider pour chaque utilisateur avant de fusionner la bascule.
$byUser = [];
foreach ($desired as $row) {
    $byUser[$row['user_id']][] = $row;
}

$divergences = 0;
foreach ($users->findAll() as $user) {
    $userId = (int) $user['id'];
    $rows   = $byUser[$userId] ?? [];

    $oldIsAdmin = !empty($user['is_admin']);
    $oldManagedStoreIds = array_values(array_unique(array_map(
        fn($m) => (int) $m['store_id'],
        array_filter($storeUsers->findByUser($userId), fn($m) => in_array($m['role'] ?? '', ['admin', 'manager'], true))
    )));
    sort($oldManagedStoreIds);

    $newIsOwner = !empty(array_filter($rows, fn($r) => $r['scope_type'] === 'global' && $r['role_id'] === (int) $ownerRole['id']));
    $newManagedStoreIds = array_values(array_unique(array_map(
        fn($r) => (int) $r['scope_id'],
        array_filter($rows, fn($r) => $r['scope_type'] === 'store' && $r['role_id'] === (int) $managerRole['id'])
    )));
    sort($newManagedStoreIds);

    if ($oldIsAdmin !== $newIsOwner || $oldManagedStoreIds !== $newManagedStoreIds) {
        $divergences++;
        printf(
            "DIVERGENCE user #%d (%s) : is_admin=%s→owner=%s, managed_stores=[%s]→[%s]\n",
            $userId,
            $user['email'] ?? '',
            $oldIsAdmin ? 'oui' : 'non',
            $newIsOwner ? 'oui' : 'non',
            implode(',', $oldManagedStoreIds),
            implode(',', $newManagedStoreIds),
        );
    }
}

printf("%d utilisateur(s) analysé(s), %d affectation(s) à créer, %d divergence(s).\n", count($users->findAll()), count($desired), $divergences);

if ($dryRun) {
    echo "--dry-run : aucune écriture effectuée.\n";
    exit($divergences > 0 ? 1 : 0);
}

if ($divergences > 0) {
    fwrite(STDERR, "Des divergences ont été détectées — corrige-les avant d'appliquer le backfill (relance en --dry-run pour les revoir).\n");
    exit(1);
}

$created = 0;
foreach ($desired as $row) {
    $result = $assignments->assign($row['user_id'], $row['role_id'], $row['scope_type'], $row['scope_id']);
    if ($result !== null) {
        $created++;
    }
}

printf("%d affectation(s) de rôle créée(s) (les affectations déjà présentes sont ignorées).\n", $created);
