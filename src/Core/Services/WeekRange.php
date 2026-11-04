<?php

declare(strict_types=1);

namespace kintai\Core\Services;

use DateTimeImmutable;

/**
 * Calcul du premier jour d'une semaine selon le jour de début propre au magasin.
 */
final class WeekRange
{
    /**
     * @param int $weekStartDay 1 = lundi … 7 = dimanche (ISO), borné si hors plage
     */
    public static function firstDay(DateTimeImmutable $anchor, int $weekStartDay): DateTimeImmutable
    {
        $weekStartDay = max(1, min(7, $weekStartDay));
        $offset       = ((int) $anchor->format('N') - $weekStartDay + 7) % 7;

        return $anchor->modify('-' . $offset . ' days');
    }
}
