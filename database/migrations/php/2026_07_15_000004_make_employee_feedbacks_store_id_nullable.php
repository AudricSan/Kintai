<?php

declare(strict_types=1);

namespace kintai\Database\Migrations;

use kintai\Core\Database\Migration;
use Illuminate\Database\Schema\Blueprint;

/**
 * L'Owner (et tout rôle global sans adhésion à un store — voir install.php,
 * qui n'assigne que role_assignments, pas de ligne store_user) ne peut être
 * rattaché à aucun store : le feedback modal étant désormais accessible
 * aussi côté admin/owner (voir FeedbackController::submit()), store_id doit
 * pouvoir rester vide pour ces comptes plutôt que de bloquer la soumission.
 */
return new class($this->capsule) extends Migration {
    public function up(): void
    {
        $this->schema()->table('employee_feedbacks', function (Blueprint $table) {
            $table->dropForeign(['store_id']);
            $table->dropIndex(['store_id', 'created_at']);
        });
        $this->schema()->table('employee_feedbacks', function (Blueprint $table) {
            $table->dropColumn(['store_id']);
        });
        $this->schema()->table('employee_feedbacks', function (Blueprint $table) {
            $table->integer('store_id')->nullable()->after('user_id');
            $table->foreign('store_id')->references('id')->on('stores')->onDelete('cascade');
            $table->index(['store_id', 'created_at']);
        });
    }

    public function down(): void
    {
        $this->schema()->table('employee_feedbacks', function (Blueprint $table) {
            $table->dropForeign(['store_id']);
            $table->dropIndex(['store_id', 'created_at']);
        });
        $this->schema()->table('employee_feedbacks', function (Blueprint $table) {
            $table->dropColumn(['store_id']);
        });
        $this->schema()->table('employee_feedbacks', function (Blueprint $table) {
            $table->integer('store_id')->after('user_id');
            $table->foreign('store_id')->references('id')->on('stores')->onDelete('cascade');
            $table->index(['store_id', 'created_at']);
        });
    }
};
