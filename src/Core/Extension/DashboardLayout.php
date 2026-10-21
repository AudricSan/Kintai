<?php

declare(strict_types=1);

namespace kintai\Core\Extension;

/**
 * Disposition d'un tableau de bord choisie par l'utilisateur : ordre des widgets et largeur de chacun (demi ou pleine).
 * Enregistrée par utilisateur et par tableau de bord (user_dashboard_prefs.layout) ; sans disposition enregistrée, les
 * widgets du Core viennent d'abord dans leur ordre, puis ceux des bundles, avec leur largeur par défaut.
 *
 * Forme enregistrée : {"order": ["kpi_counters", …], "widths": {"pending_timeoff": "half", …}}.
 */
final class DashboardLayout
{
    public const HALF = 'half';
    public const FULL = 'full';

    /** Clé de widget : celle d'un widget (41 caractères au plus), éventuellement préfixée « emp_ » côté gérant. */
    public const KEY = '/^[a-z][a-z0-9_]{1,47}$/';

    /**
     * Disposition nettoyée : clés bien formées et sans doublon, largeurs limitées à HALF/FULL.
     *
     * @return array{order: list<string>, widths: array<string, string>}
     */
    public static function normalize(mixed $raw): array
    {
        $raw = is_array($raw) ? $raw : [];
        $order = [];
        foreach (is_array($raw['order'] ?? null) ? $raw['order'] : [] as $key) {
            if (self::isKey($key) && !in_array($key, $order, true)) {
                $order[] = $key;
            }
        }
        $widths = [];
        foreach (is_array($raw['widths'] ?? null) ? $raw['widths'] : [] as $key => $width) {
            if (self::isKey($key) && in_array($width, [self::HALF, self::FULL], true)) {
                $widths[$key] = $width;
            }
        }
        return ['order' => $order, 'widths' => $widths];
    }

    /**
     * Disposition à afficher : ordre et largeur de chaque widget proposé.
     *
     * @param list<string>          $keys     Widgets proposés, dans l'ordre par défaut (Core puis bundles)
     * @param array{order: list<string>, widths: array<string, string>}|null $layout Disposition enregistrée
     * @param array<string, string> $defaults Largeurs par défaut
     * @return array{order: list<string>, widths: array<string, string>}
     */
    public static function resolve(array $keys, ?array $layout, array $defaults = []): array
    {
        return ['order' => self::order($keys, $layout), 'widths' => self::widths($keys, $layout, $defaults)];
    }

    /**
     * Ordre d'affichage des widgets proposés : ordre enregistré pour ceux qu'il connaît, puis les autres dans l'ordre
     * par défaut (un widget apparu depuis, par exemple d'un bundle installé ensuite, arrive en fin de page).
     *
     * @param list<string> $keys Widgets proposés, dans l'ordre par défaut (Core puis bundles)
     * @param array{order: list<string>, widths: array<string, string>}|null $layout
     * @return list<string>
     */
    public static function order(array $keys, ?array $layout): array
    {
        $saved = array_values(array_intersect($layout['order'] ?? [], $keys));
        return array_values(array_merge($saved, array_diff($keys, $saved)));
    }

    /**
     * Largeur de chaque widget proposé : choix de l'utilisateur, sinon la largeur par défaut, sinon pleine largeur.
     *
     * @param list<string>           $keys
     * @param array{order: list<string>, widths: array<string, string>}|null $layout
     * @param array<string, string>  $defaults Largeurs par défaut (ex. deux widgets du Core côte à côte)
     * @return array<string, string>
     */
    public static function widths(array $keys, ?array $layout, array $defaults = []): array
    {
        $widths = [];
        foreach ($keys as $key) {
            $widths[$key] = $layout['widths'][$key] ?? $defaults[$key] ?? self::FULL;
        }
        return $widths;
    }

    /**
     * Nouvelle disposition après un déplacement fait sur le tableau de bord. La page n'envoie que les widgets
     * affichés : ceux qui ne le sont pas (désactivés, sans permission, bundle désactivé) gardent leur place, les
     * widgets affichés reprennent les places qu'ils occupaient, dans leur nouvel ordre.
     *
     * @param list<string>          $keys     Widgets proposés, dans l'ordre par défaut
     * @param array{order: list<string>, widths: array<string, string>}|null $previous
     * @param list<string>          $visible  Widgets affichés, dans l'ordre choisi
     * @param array<string, string> $widths   Largeurs choisies pour les widgets affichés
     * @return array{order: list<string>, widths: array<string, string>}
     */
    public static function update(array $keys, ?array $previous, array $visible, array $widths): array
    {
        $previous = self::normalize($previous);
        $submitted = self::normalize(['order' => $visible, 'widths' => $widths]);
        $visible = array_values(array_intersect($submitted['order'], $keys));

        // Ordre complet connu : celui enregistré (y compris les widgets d'un bundle désactivé), complété par les
        // widgets proposés qu'il ne connaît pas encore.
        $full = array_values(array_unique(array_merge($previous['order'], $keys)));
        $queue = $visible;
        $order = [];
        foreach ($full as $key) {
            $order[] = in_array($key, $visible, true) ? array_shift($queue) : $key;
        }

        $newWidths = $previous['widths'];
        foreach ($submitted['widths'] as $key => $width) {
            if (in_array($key, $keys, true)) {
                $newWidths[$key] = $width;
            }
        }
        return ['order' => $order, 'widths' => $newWidths];
    }

    /**
     * Disposition après l'ajout d'un widget au tableau de bord : il passe en dernière position, les autres widgets
     * gardent leur place et leur largeur.
     *
     * @param list<string> $keys Widgets proposés, dans l'ordre par défaut
     * @param array{order: list<string>, widths: array<string, string>}|null $previous
     * @return array{order: list<string>, widths: array<string, string>}
     */
    public static function moveToEnd(array $keys, ?array $previous, string $key): array
    {
        $previous = self::normalize($previous);
        $order = array_values(array_diff(array_unique(array_merge($previous['order'], $keys)), [$key]));
        $order[] = $key;
        return self::normalize(['order' => $order, 'widths' => $previous['widths']]);
    }

    private static function isKey(mixed $key): bool
    {
        return is_string($key) && preg_match(self::KEY, $key) === 1;
    }
}
