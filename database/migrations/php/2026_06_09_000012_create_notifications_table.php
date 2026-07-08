<?php

declare(strict_types=1);

namespace kintai\Database\Migrations;

use kintai\Core\Database\Migration;
use Illuminate\Database\Schema\Blueprint;

return new class($this->capsule) extends Migration {
    public function up(): void
    {
        if ($this->schema()->hasTable('notifications')) {
            return;
        }
        $this->schema()->create('notifications', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('user_id');
            $table->string('type');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['user_id', 'read_at']);
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        $this->schema()->dropIfExists('notifications');
    }
};
