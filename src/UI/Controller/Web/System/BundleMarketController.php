<?php

declare(strict_types=1);

namespace kintai\UI\Controller\Web\System;

use kintai\Core\Repositories\BundleRegistryRepositoryInterface;
use kintai\Core\Request;
use kintai\Core\Response;
use kintai\Core\Services\AuditLogger;
use kintai\UI\Controller\Web\HasBaseUrl;
use kintai\UI\ViewRenderer;

/**
 * Gère les registries de bundles ajoutés par l'Owner (façon dépôts d'add-ons
 * Home Assistant) : liste/ajout/suppression pour l'instant. Distinct de
 * BundleSettingsController, qui active/désactive un bundle déjà présent sur
 * le disque — celui-ci ne fait qu'enregistrer des sources de découverte. Le
 * catalogue agrégé et l'installation proprement dite arriveront dans une
 * prochaine itération, une fois BundleInstallerService disponible.
 */
final class BundleMarketController
{
    use HasBaseUrl;

    public function __construct(
        private readonly ViewRenderer $view,
        private readonly BundleRegistryRepositoryInterface $registries,
        private readonly AuditLogger $auditLogger,
    ) {}

    /** GET /admin/bundles/registries */
    public function index(Request $request): Response
    {
        return Response::html($this->view->render('system.bundle-registries', [
            'title'      => __('bundle_registries'),
            'registries' => $this->registries->all(),
            'error'      => $request->query('error'),
            'success'    => $request->query('success'),
        ], 'layout.app'));
    }

    /** POST /admin/bundles/registries */
    public function store(Request $request): Response
    {
        $name = trim((string) $request->post('name', ''));
        $url = trim((string) $request->post('url', ''));

        if ($name === '' || mb_strlen($name) > 150 || !filter_var($url, FILTER_VALIDATE_URL) || !str_starts_with($url, 'https://')) {
            return Response::redirect($this->base() . '/admin/bundles/registries?error=invalid');
        }

        if ($this->registries->existsByUrl($url)) {
            return Response::redirect($this->base() . '/admin/bundles/registries?error=duplicate');
        }

        $created = $this->registries->create($name, $url);

        $this->auditLogger->log($request, 'bundle_registry.created', 'bundle_registry', $created['id'], [
            'name' => $name,
            'url'  => $url,
        ]);

        return Response::redirect($this->base() . '/admin/bundles/registries?success=created');
    }

    /** POST /admin/bundles/registries/{id}/delete */
    public function destroy(Request $request): Response
    {
        $id = (int) $request->param('id');
        $registry = $this->registries->find($id);
        if (!$registry) {
            return Response::redirect($this->base() . '/admin/bundles/registries');
        }
        if ($registry['is_official']) {
            return Response::redirect($this->base() . '/admin/bundles/registries?error=delete_official_forbidden');
        }

        $this->registries->delete($id);

        $this->auditLogger->log($request, 'bundle_registry.deleted', 'bundle_registry', $id, [
            'name' => $registry['name'],
            'url'  => $registry['url'],
        ]);

        return Response::redirect($this->base() . '/admin/bundles/registries?success=deleted');
    }
}
