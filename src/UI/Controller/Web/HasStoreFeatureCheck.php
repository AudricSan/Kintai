<?php

declare(strict_types=1);

namespace kintai\UI\Controller\Web;

use kintai\Core\Request;
use kintai\Core\Response;

/**
 * Garde d'accès employé pour les fonctionnalités activables par store
 * (Congés, Échanges, Bourse aux shifts, Messagerie...). Nécessite
 * $this->storeUsers (StoreUserRepositoryInterface), $this->stores
 * (StoreRepositoryInterface) et le trait HasBaseUrl.
 */
trait HasStoreFeatureCheck
{
    protected function assertFeature(Request $request, string $feature): ?Response
    {
        $user        = $request->getAttribute('auth_user');
        $memberships = $this->storeUsers->findByUser((int) ($user['id'] ?? 0));
        if (empty($memberships)) {
            return null;
        }
        $features = $this->stores->getFeatures((int) $memberships[0]['store_id']);
        if ($features === [] || in_array($feature, $features, true)) {
            return null;
        }
        return Response::redirect($this->base() . '/employee?error=feature_disabled');
    }
}
