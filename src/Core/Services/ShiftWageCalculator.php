<?php

declare(strict_types=1);

namespace kintai\Core\Services;

/**
 * ShiftWageCalculator — décomposition d'un shift par tranches horaires
 *
 * Un shift peut chevaucher plusieurs shift_types (tranches horaires).
 * Ce service calcule les minutes dans chaque tranche et le salaire estimé.
 *
 * Gère :
 *   - Les shifts qui traversent minuit (ex : 22:00 → 06:00)
 *   - Les tranches qui traversent minuit (ex : Nuit 22:00 → 06:00)
 *   - Le type dominant (tranche avec le plus de minutes)
 */
final class ShiftWageCalculator
{
    /**
     * Calcule le salaire estimé et la décomposition par tranche.
     *
     * @param string   $startTime  Format "HH:MM"
     * @param string   $endTime    Format "HH:MM"
     * @param array    $shiftTypes Types actifs du store :
     *                             [['id', 'name', 'start_time', 'end_time', 'hourly_rate'], ...]
     * @param int|null $netMinutes Minutes rémunérées réelles (après déduction de la pause).
     *                             Si fourni, les minutes de chaque tranche sont réduites
     *                             proportionnellement. Null = utiliser la durée brute.
     * @return array{
     *     estimated_salary: float,
     *     dominant_type_id: int|null,
     *     breakdown: list<array{shift_type_id:int, name:string, minutes:int, rate:float, amount:float}>
     * }
     */
    public function calculate(string $startTime, string $endTime, array $shiftTypes, ?int $netMinutes = null): array
    {
        $shiftStart = $this->toMinutes($startTime);
        $shiftEnd   = $this->toMinutes($endTime);

        // Shift qui traverse minuit
        if ($shiftEnd <= $shiftStart) {
            $shiftEnd += 1440;
        }

        $breakdown    = [];
        $brutTotal    = 0;
        $dominantId   = null;
        $dominantMins = 0;

        foreach ($shiftTypes as $type) {
            $typeStart = $this->toMinutes((string) ($type['start_time'] ?? '00:00'));
            $typeEnd   = $this->toMinutes((string) ($type['end_time']   ?? '00:00'));

            // Tranche qui traverse minuit (ex : Nuit 22:00 → 06:00)
            if ($typeEnd <= $typeStart) {
                $typeEnd += 1440;
            }

            // Essayer les deux positions possibles du type dans la fenêtre 0-2880
            $minutes = $this->overlap($shiftStart, $shiftEnd, $typeStart, $typeEnd);
            if ($minutes === 0) {
                // Décaler le type de +1440 (lendemain) pour les shifts tardifs
                $minutes = $this->overlap($shiftStart, $shiftEnd, $typeStart + 1440, $typeEnd + 1440);
            }

            if ($minutes <= 0) continue;

            $brutTotal += $minutes;
            $breakdown[] = [
                'shift_type_id' => (int) $type['id'],
                'name'          => (string) $type['name'],
                'minutes'       => $minutes,
                'rate'          => (float) ($type['hourly_rate'] ?? 0),
                'amount'        => 0.0, // calculé après réduction éventuelle
            ];

            if ($minutes > $dominantMins) {
                $dominantMins = $minutes;
                $dominantId   = (int) $type['id'];
            }
        }

        // Réduction proportionnelle si des minutes de pause ont été déduites
        if ($netMinutes !== null && $brutTotal > 0 && $netMinutes < $brutTotal) {
            $factor = $netMinutes / $brutTotal;
            foreach ($breakdown as &$b) {
                $b['minutes'] = (int) round($b['minutes'] * $factor);
            }
            unset($b);
        }

        // Calcul des montants après réduction
        $totalSalary = 0.0;
        foreach ($breakdown as &$b) {
            $b['amount']  = $b['rate'] > 0 ? round($b['rate'] * $b['minutes'] / 60, 2) : 0.0;
            $totalSalary += $b['amount'];
        }
        unset($b);

        // Trier le breakdown par shift_type_id (ordre de définition)
        usort($breakdown, fn($a, $b) => $a['shift_type_id'] <=> $b['shift_type_id']);

        return [
            'estimated_salary' => round($totalSalary, 2),
            'dominant_type_id' => $dominantId,
            'breakdown'        => $breakdown,
        ];
    }

    private function toMinutes(string $time): int
    {
        $parts = explode(':', $time);
        return (int) ($parts[0] ?? 0) * 60 + (int) ($parts[1] ?? 0);
    }

    private function overlap(int $aStart, int $aEnd, int $bStart, int $bEnd): int
    {
        return max(0, min($aEnd, $bEnd) - max($aStart, $bStart));
    }
}
