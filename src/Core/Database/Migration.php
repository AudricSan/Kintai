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

    /**
     * Une migration "seed" insère des données structurelles idempotentes (elle se
     * protège elle-même contre une double insertion, ex. "le rôle existe déjà ?")
     * plutôt que du schéma pur ou une migration de données à usage unique. Ce
     * marqueur permet à AppResetService::resetFactory() de savoir, sans connaître
     * aucun nom de migration, lesquelles doivent rejouer après avoir vidé les
     * données — sinon leur garde d'idempotence empêcherait tout reseed pour
     * toujours (la table migrations, elle, n'est jamais vidée par un reset).
     */
    public function isSeed(): bool
    {
        return false;
    }

    protected function schema()
    {
        return $this->capsule->getConnection()->getSchemaBuilder();
    }
}
