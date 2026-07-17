<?php

declare(strict_types=1);

namespace kintai\Bundles\Feedback;

use kintai\Core\Bundle;
use kintai\Core\Repositories\FeedbackRepositoryInterface;
use kintai\Core\Repositories\DatabaseFeedbackRepository;

/**
 * Comme store-photos/shift-claim, aucun autre composant Core ne dépend de
 * FeedbackRepositoryInterface : le repository est enregistré par ce bundle,
 * pas par le Core. Désactiver "feedback" retire entièrement la fonctionnalité
 * (soumission employé, liste/suppression admin, /api/v1/feedbacks). Voir
 * aussi src/UI/View/layout/partials/feedback-modal.php : ce partial reste
 * dans le Core (inclus directement par app.php pour tout utilisateur
 * authentifié, employé comme admin/owner) mais son inclusion est
 * conditionnée par FeatureManager via AuthMiddleware ($feedback_enabled),
 * pour ne pas afficher un formulaire dont la cible POST n'existe plus quand
 * ce bundle est désactivé.
 */
final class FeedbackBundle extends Bundle
{
    public function getName(): string
    {
        return 'feedback';
    }

    public function getLabel(): string
    {
        return __('bundle_feedback');
    }

    public function getDescription(): string
    {
        return __('bundle_feedback_desc');
    }

    public function register(): void
    {
        $this->registerServices();
        $this->loadViewsFrom($this->getPath() . '/Views', 'feedback');
        $this->loadRoutesFrom($this->getPath() . '/routes.php');
    }

    private function registerServices(): void
    {
        $container = $this->app->container();

        $container->singleton(
            FeedbackRepositoryInterface::class,
            fn() => new DatabaseFeedbackRepository()
        );
    }
}
