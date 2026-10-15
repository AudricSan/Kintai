<?php

declare(strict_types=1);

namespace kintai\Core\Database;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Eloquent\Model;

/**
 * Horodatage des créations dans le fuseau de l'instance (audit n°8, I5 ; décision : pour l'avenir, sans réécrire
 * l'historique).
 *
 * Beaucoup de colonnes created_at ont une valeur par défaut fournie par la base (useCurrent(), 55 colonnes du Core et
 * des bundles). Or CURRENT_TIMESTAMP est en UTC sous SQLite, alors que PHP écrit les autres dates dans le fuseau de
 * l'application (APP_TIMEZONE) : dans une même ligne, created_at et claimed_at avaient 9 h d'écart, et la date
 * « envoyé le » d'un congé changeait selon qu'il venait du web ou de l'API.
 *
 * Répartiteur d'événements minimal pour Eloquent (illuminate/events n'est pas installé) : à la création de tout modèle,
 * Core ou bundle, created_at reçoit date('Y-m-d H:i:s') si la table a cette colonne et que rien ne l'a renseignée.
 * Les autres événements sont ignorés.
 */
final class InstanceTimestamps implements Dispatcher
{
    /** @var array<string, bool> table => possède created_at */
    private array $hasCreatedAt = [];

    public function until($event, $payload = [])
    {
        if (is_string($event) && str_starts_with($event, 'eloquent.creating: ') && $payload instanceof Model) {
            $this->fillCreatedAt($payload);
        }
        return null;
    }

    public function dispatch($event, $payload = [], $halt = false)
    {
        return $halt ? $this->until($event, $payload) : null;
    }

    private function fillCreatedAt(Model $model): void
    {
        if ($model->getAttribute('created_at') !== null) {
            return;
        }
        $table = $model->getTable();
        if (!array_key_exists($table, $this->hasCreatedAt)) {
            try {
                $this->hasCreatedAt[$table] = $model->getConnection()->getSchemaBuilder()->hasColumn($table, 'created_at');
            } catch (\Throwable) {
                $this->hasCreatedAt[$table] = false;
            }
        }
        if ($this->hasCreatedAt[$table]) {
            $model->setAttribute('created_at', date('Y-m-d H:i:s'));
        }
    }

    public function listen($events, $listener = null)
    {
    }

    public function hasListeners($eventName)
    {
        return false;
    }

    public function subscribe($subscriber)
    {
    }

    public function push($event, $payload = [])
    {
    }

    public function flush($event)
    {
    }

    public function forget($event)
    {
    }

    public function forgetPushed()
    {
    }
}
