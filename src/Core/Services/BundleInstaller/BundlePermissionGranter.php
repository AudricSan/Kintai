<?php

declare(strict_types=1);

namespace kintai\Core\Services\BundleInstaller;

use kintai\Core\Repositories\RoleRepositoryInterface;

/**
 * À la première installation d'un bundle (assistant de configuration ou catalogue), accorde au rôle Manager les
 * catégories de permissions que le bundle déclare (Bundle::permissions(), contrat 1.2). Le Manager agit sur ses
 * magasins (portée de ses affectations) ; l'Owner peut retirer ces droits dans Rôles. Une mise à jour ne réaccorde
 * rien : un droit retiré par l'Owner le reste.
 */
final class BundlePermissionGranter
{
    private const NAME = '/^[a-z][a-z0-9_]{1,40}$/';

    public function __construct(private readonly RoleRepositoryInterface $roles) {}

    /**
     * Catégories déclarées par la classe d'entrée d'un bundle (instanciée sans constructeur, comme à la découverte),
     * filtrées comme le registre des extensions le fait : noms mal formés ignorés.
     *
     * @return array<string, list<string>>
     */
    public static function declaredPermissions(string $entryClass): array
    {
        $declared = (new \ReflectionClass($entryClass))->newInstanceWithoutConstructor()->permissions();
        $categories = [];
        foreach (is_array($declared) ? $declared : [] as $category => $actions) {
            if (!is_string($category) || preg_match(self::NAME, $category) !== 1 || !is_array($actions)) {
                continue;
            }
            $valid = array_values(array_filter($actions, static fn($a): bool => is_string($a) && preg_match(self::NAME, $a) === 1));
            if ($valid !== []) {
                $categories[$category] = array_values(array_unique($valid));
            }
        }
        return $categories;
    }

    /**
     * Ajoute ces permissions au rôle Manager (rôle système « manager »), sans toucher à celles qu'il a déjà ni à leur
     * portée.
     *
     * @param array<string, list<string>> $categories
     * @return list<string> Clés nouvellement accordées
     */
    public function grantToManager(array $categories): array
    {
        $manager = $this->roles->findBySlug('manager');
        if ($manager === null) {
            return [];
        }
        $roleId = (int) $manager['id'];
        $current = $this->roles->getPermissions($roleId);
        $new = [];
        foreach ($categories as $category => $actions) {
            foreach ($actions as $action) {
                $key = $category . '.' . $action;
                if (!in_array($key, $current, true) && !in_array($key, $new, true)) {
                    $new[] = $key;
                }
            }
        }
        if ($new !== []) {
            $this->roles->savePermissions($roleId, array_merge($current, $new), $this->roles->getGlobalPermissionKeys($roleId));
        }
        return $new;
    }
}
