<?php

declare(strict_types=1);

namespace kintai\Database\Migrations;

use kintai\Core\Database\Migration;
use Illuminate\Database\Schema\Blueprint;

/**
 * users.is_admin et store_user.role/is_manager sont des colonnes historiques
 * gardées en double-écriture depuis la bascule vers le RBAC dynamique
 * (role_assignments, juillet 2026, task/mermission.md phase 3). AuthService/
 * PermissionService n'en ont jamais dépendu pour une décision d'autorisation
 * (role_assignments fait déjà foi) ; seuls restaient de l'affichage/tri et un
 * sélecteur de formulaire, migrés vers le RBAC dynamique dans cette même
 * passe (phase 4). Rien ne les lit ni ne les écrit plus.
 */
return new class($this->capsule) extends Migration {
    public function up(): void
    {
        if ($this->schema()->hasColumn('users', 'is_admin')) {
            $this->schema()->table('users', function (Blueprint $table) {
                $table->dropColumn('is_admin');
            });
        }
        $this->schema()->table('store_user', function (Blueprint $table) {
            if ($this->schema()->hasColumn('store_user', 'role')) {
                // L'index composite (store_id, role) référence la colonne : le
                // driver ne peut pas la dropper tant qu'il existe encore.
                $table->dropIndex('store_user_store_id_role_index');
                $table->dropColumn('role');
            }
            if ($this->schema()->hasColumn('store_user', 'is_manager')) {
                $table->dropColumn('is_manager');
            }
        });
    }

    public function down(): void
    {
        // Colonnes legacy retirées après migration complète vers le RBAC
        // dynamique (role_assignments) : rien à recréer, plus aucun code ne
        // les lit ni ne les écrit.
    }
};
