<?php

declare(strict_types=1);

namespace kintai\Core\BundleContract;

use kintai\Core\Application;

/**
 * Point de contact stable pour tout auteur de bundle, interne ou tiers.
 *
 * Tout ce qui vit sous kintai\Core\BundleContract suit son propre
 * versionnage sémantique, annoncé dans une section dédiée du CHANGELOG :
 * ajouter une méthode optionnelle est mineur, changer une signature ou en
 * supprimer une est majeur et documenté. Le reste de kintai\Core\* peut
 * évoluer entre deux versions mineures sans préavis — un bundle ne doit
 * dépendre que de ce qui est déclaré ici (plus des interfaces de
 * src/Core/Repositories/*Interface.php, déjà un contrat stable par
 * construction).
 */
abstract class Bundle
{
    protected Application $app;
    protected string $path;

    public function __construct(Application $app)
    {
        $this->app = $app;
        $this->path = $this->resolvePath();
    }

    /**
     * Identifiant unique du bundle (slug). Doit être identique au champ
     * "slug" de bundle.json pour un bundle installé dynamiquement.
     */
    abstract public function getName(): string;

    /**
     * Enregistre services, routes ou vues.
     */
    abstract public function register(): void;

    /**
     * Version du bundle. Un bundle legacy (monorepo, sans bundle.json) peut
     * laisser la valeur par défaut ; un bundle installé dynamiquement doit
     * la faire correspondre au champ "version" de son bundle.json.
     */
    public function getVersion(): string
    {
        return '0.0.0';
    }

    /**
     * Libellé lisible affiché dans /admin/bundles. À surcharger pour traduire.
     */
    public function getLabel(): string
    {
        return ucwords(str_replace(['-', '_'], ' ', $this->getName()));
    }

    /**
     * Description lisible affichée dans /admin/bundles. À surcharger pour traduire.
     */
    public function getDescription(): string
    {
        return '';
    }

    /**
     * Boot du bundle, une fois tous les bundles enregistrés.
     */
    public function boot(): void
    {
        // Optionnel
    }

    /**
     * Chemin racine du bundle sur le disque.
     */
    public function getPath(): string
    {
        return $this->path;
    }

    protected function resolvePath(): string
    {
        $reflector = new \ReflectionClass(static::class);
        return dirname($reflector->getFileName());
    }

    /**
     * Aide à l'enregistrement des routes depuis un fichier.
     */
    protected function loadRoutesFrom(string $path): void
    {
        $router = $this->app->router();
        $container = $this->app->container();
        require $path;
    }

    /**
     * Aide à l'enregistrement des vues sous un namespace.
     */
    protected function loadViewsFrom(string $path, string $namespace): void
    {
        $viewRenderer = $this->app->container()->make(\kintai\UI\ViewRenderer::class);
        $viewRenderer->addNamespace($namespace, $path);
    }
}
