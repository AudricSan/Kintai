<?php

declare(strict_types=1);

namespace kintai\Core\Services;

use kintai\Core\Repositories\ShiftRepositoryInterface;
use kintai\Core\Repositories\ShiftTypeRepositoryInterface;
use kintai\Core\Repositories\StoreRepositoryInterface;
use kintai\Core\Repositories\StoreUserRepositoryInterface;
use kintai\Core\Repositories\UserShiftTypeRateRepositoryInterface;

final class EmployeeStatsService
{
    private const FR_DAYS = ['Mon'=>'Lun','Tue'=>'Mar','Wed'=>'Mer','Thu'=>'Jeu','Fri'=>'Ven','Sat'=>'Sam','Sun'=>'Dim'];
    private const FR_MONTHS = ['Jan'=>'jan.','Feb'=>'fév.','Mar'=>'mars','Apr'=>'avr.','May'=>'mai','Jun'=>'juin',
                               'Jul'=>'juil.','Aug'=>'août','Sep'=>'sep.','Oct'=>'oct.','Nov'=>'nov.','Dec'=>'déc.'];
    private const FR_MONTHS_FULL = [
        '01'=>'Janvier','02'=>'Février','03'=>'Mars','04'=>'Avril','05'=>'Mai','06'=>'Juin',
        '07'=>'Juillet','08'=>'Août','09'=>'Septembre','10'=>'Octobre','11'=>'Novembre','12'=>'Décembre',
    ];

    public function __construct(
        private readonly ShiftRepositoryInterface $shifts,
        private readonly ShiftTypeRepositoryInterface $shiftTypes,
        private readonly UserShiftTypeRateRepositoryInterface $userRates,
        private readonly StoreUserRepositoryInterface $storeUsers,
        private readonly StoreRepositoryInterface $stores,
    ) {}

    public function calculate(int $userId, string $month = ''): array
    {
        if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
            $month = date('Y-m');
        }
        $monthStart = $month . '-01';
        $monthEnd   = date('Y-m-t', strtotime($monthStart));
        $prevMonth  = date('Y-m', strtotime($monthStart . ' -1 month'));
        $nextMonth  = date('Y-m', strtotime($monthStart . ' +1 month'));

        $weekSet = [];
        $cur = new \DateTime($monthStart);
        $endDt = new \DateTime($monthEnd);
        while ($cur <= $endDt) {
            $weekSet[$cur->format('o-W')] = true;
            $cur->modify('+1 day');
        }
        $numWeeks = max(1, count($weekSet));

        $typesMap = [];
        foreach ($this->shiftTypes->findAll() as $t) {
            $typesMap[(int) $t['id']] = $t;
        }
        $personalRates = [];
        foreach ($this->userRates->findByUser($userId) as $r) {
            $personalRates[(int) $r['shift_type_id']] = (float) $r['hourly_rate'];
        }

        $memberships = $this->storeUsers->findByUser($userId);
        $currency    = 'JPY';
        if (!empty($memberships)) {
            $store    = $this->stores->findById((int) $memberships[0]['store_id']);
            $currency = strtoupper(trim($store['currency'] ?? 'JPY'));
        }

        $monthMinutes = 0;
        $estimatedPay = 0.0;
        $hasRate      = false;
        $shiftDetails = [];

        foreach ($this->shifts->findByUser($userId) as $s) {
            $d = $s['shift_date'] ?? '';
            if ($d < $monthStart || $d > $monthEnd) {
                continue;
            }
            [$sh, $sm] = explode(':', substr($s['start_time'] ?? '00:00', 0, 5));
            [$eh, $em] = explode(':', substr($s['end_time']   ?? '00:00', 0, 5));
            $startMin  = (int) $sh * 60 + (int) $sm;
            $endMin    = (int) $eh * 60 + (int) $em;
            if (!empty($s['cross_midnight']) || $endMin <= $startMin) {
                $endMin += 24 * 60;
            }
            $pauseMin      = (int) ($s['pause_minutes'] ?? 0);
            $netMin        = max(0, $endMin - $startMin - $pauseMin);
            $monthMinutes += $netMin;

            $tid  = (int) ($s['shift_type_id'] ?? 0);
            $rate = $personalRates[$tid] ?? (float) ($typesMap[$tid]['hourly_rate'] ?? 0);
            $pay  = ($netMin / 60) * $rate;
            if ($rate > 0) {
                $hasRate = true;
            }
            $estimatedPay += $pay;

            $dt       = new \DateTimeImmutable($d);
            $dateLabel = ($this::FR_DAYS[$dt->format('D')] ?? $dt->format('D'))
                . ' ' . $dt->format('j')
                . ' ' . ($this::FR_MONTHS[$dt->format('M')] ?? $dt->format('M'));
            $h = intdiv($netMin, 60);
            $m = $netMin % 60;
            $shiftDetails[] = [
                'date'          => $d,
                'date_label'    => $dateLabel,
                'start'         => substr($s['start_time'] ?? '—', 0, 5),
                'end'           => substr($s['end_time']   ?? '—', 0, 5),
                'type_name'     => $typesMap[$tid]['name'] ?? '—',
                'net_min'       => $netMin,
                'net_hours_fmt' => $h . 'h' . str_pad((string)$m, 2, '0', STR_PAD_LEFT),
                'pause_min'     => $pauseMin,
                'rate'          => $rate,
                'rate_fmt'      => $rate > 0 ? format_currency($rate, $currency) . '/h' : '—',
                'pay_fmt'       => $rate > 0 ? format_currency($pay, $currency) : '—',
                'has_rate'      => $rate > 0,
            ];
        }

        usort($shiftDetails, fn($a, $b) => strcmp($a['date'] . $a['start'], $b['date'] . $b['start']));

        [$y, $mNum] = array_pad(explode('-', $month), 2, '');
        $monthLabel = ($this::FR_MONTHS_FULL[$mNum] ?? $mNum) . ' ' . $y;

        return [
            'hours_month'   => $monthMinutes / 60,
            'hours_week'    => ($monthMinutes / 60) / $numWeeks,
            'estimated_pay' => $estimatedPay,
            'has_rate'      => $hasRate,
            'month_label'   => $monthLabel,
            'month_key'     => $month,
            'prev_month'    => $prevMonth,
            'next_month'    => $nextMonth,
            'is_current'    => ($month === date('Y-m')),
            'currency'      => $currency,
            'shift_details' => $shiftDetails,
        ];
    }
}
