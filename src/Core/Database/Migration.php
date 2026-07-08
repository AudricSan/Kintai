<?php

declare(strict_types=1);

namespace kintai\Core\Database;

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;

abstract class Migration
{
    protected Capsule $capsule;

    public function __construct(Capsule $capsule)
    {
        $this->capsule = $capsule;
    }

    abstract public function up(): void;
    abstract public function down(): void;

    protected function schema()
    {
        return $this->capsule->getConnection()->getSchemaBuilder();
    }
}
