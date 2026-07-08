<?php

declare(strict_types=1);

namespace kintai\Core\Services;

final class IcalService
{
    /**
     * Génère le contenu VCALENDAR (RFC 5545) pour un employé dans un store.
     *
     * @param array $shifts   Shifts de l'employé dans ce store (déjà filtrés par store_id)
     * @param array $timeoffs Congés approuvés de l'employé dans ce store (déjà filtrés)
     * @param array $store    Ligne du store (id, name, timezone)
     * @param array $user     Ligne de l'utilisateur (id, first_name, last_name)
     * @param array $types    Map id => shift_type (pour le SUMMARY)
     */
    public function build(array $shifts, array $timeoffs, array $store, array $user, array $types = []): string
    {
        $tz        = $store['timezone'] ?? 'UTC';
        $storeName = $store['name'] ?? 'Store';
        $calName   = $storeName . ' — ' . ($user['last_name'] ?? '') . ' ' . ($user['first_name'] ?? '');
        $dtstamp   = gmdate('Ymd\THis\Z');

        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Kintai//Kintai//EN',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'X-WR-CALNAME:' . $this->escapeText($calName),
            'X-WR-TIMEZONE:' . $tz,
            'REFRESH-INTERVAL;VALUE=DURATION:PT1H',
            'X-PUBLISHED-TTL:PT1H',
        ];

        $cutoff = date('Y-m-d H:i:s', strtotime('-30 days'));

        foreach ($shifts as $shift) {
            if (!empty($shift['deleted_at'])) {
                // Inclure les shifts supprimés récemment comme CANCELLED (30 jours)
                if (($shift['deleted_at'] ?? '') >= $cutoff) {
                    $lines[] = $this->buildShiftEvent($shift, $store, $types, $dtstamp, $tz, cancelled: true);
                }
                continue;
            }
            $lines[] = $this->buildShiftEvent($shift, $store, $types, $dtstamp, $tz);
        }

        foreach ($timeoffs as $timeoff) {
            $lines[] = $this->buildTimeoffEvent($timeoff, $store, $dtstamp);
        }

        $lines[] = 'END:VCALENDAR';

        return implode("\r\n", $lines) . "\r\n";
    }

    private function buildShiftEvent(
        array $shift, array $store, array $types, string $dtstamp, string $tz,
        bool $cancelled = false
    ): string {
        $typeId   = (int) ($shift['shift_type_id'] ?? 0);
        $typeName = $types[$typeId]['name'] ?? 'Shift';
        $summary  = $typeName . ' — ' . ($store['name'] ?? '');

        $shiftDate = $shift['shift_date'] ?? date('Y-m-d');
        $startTime = str_replace(':', '', substr($shift['start_time'] ?? '00:00', 0, 5));
        $endTime   = str_replace(':', '', substr($shift['end_time'] ?? '00:00', 0, 5));

        $startDate = str_replace('-', '', $shiftDate);
        if (!empty($shift['cross_midnight'])) {
            $endDate = date('Ymd', strtotime($shiftDate . ' +1 day'));
        } else {
            $endDate = $startDate;
        }

        $uid      = 'shift-' . ($shift['id'] ?? uniqid()) . '@kintai';
        $sequence = (int) ($shift['ical_sequence'] ?? 0);
        $created  = $this->toIcalUtc($shift['created_at'] ?? null) ?? $dtstamp;
        $modified = $this->toIcalUtc($shift['updated_at'] ?? $shift['created_at'] ?? null) ?? $dtstamp;

        $description = '';
        if (!empty($shift['pause_minutes'])) {
            $description = 'Pause: ' . (int) $shift['pause_minutes'] . ' min';
        }
        if (!empty($shift['notes'])) {
            $description .= ($description ? '\n' : '') . $this->escapeText($shift['notes']);
        }

        $eventLines = [
            'BEGIN:VEVENT',
            'UID:' . $uid,
            'DTSTAMP:' . $dtstamp,
            'SEQUENCE:' . $sequence,
            'CREATED:' . $created,
            'LAST-MODIFIED:' . $modified,
            'DTSTART;TZID=' . $tz . ':' . $startDate . 'T' . $startTime . '00',
            'DTEND;TZID=' . $tz . ':' . $endDate . 'T' . $endTime . '00',
            'SUMMARY:' . $this->escapeText($summary),
            'LOCATION:' . $this->escapeText($store['name'] ?? ''),
            'STATUS:' . ($cancelled ? 'CANCELLED' : 'CONFIRMED'),
        ];

        if ($description !== '' && !$cancelled) {
            $eventLines[] = 'DESCRIPTION:' . $description;
        }

        $eventLines[] = 'END:VEVENT';

        return implode("\r\n", array_map(fn($l) => $this->foldLine($l), $eventLines));
    }

    private function buildTimeoffEvent(array $timeoff, array $store, string $dtstamp): string
    {
        $startDate = str_replace('-', '', $timeoff['start_date'] ?? date('Y-m-d'));
        $endDate   = date('Ymd', strtotime(($timeoff['end_date'] ?? date('Y-m-d')) . ' +1 day'));

        $uid     = 'timeoff-' . ($timeoff['id'] ?? uniqid()) . '@kintai';
        $summary = 'Congé — ' . ($store['name'] ?? '');

        $eventLines = [
            'BEGIN:VEVENT',
            'UID:' . $uid,
            'DTSTAMP:' . $dtstamp,
            'DTSTART;VALUE=DATE:' . $startDate,
            'DTEND;VALUE=DATE:' . $endDate,
            'SUMMARY:' . $this->escapeText($summary),
            'STATUS:CONFIRMED',
            'TRANSP:TRANSPARENT',
            'END:VEVENT',
        ];

        return implode("\r\n", array_map(fn($l) => $this->foldLine($l), $eventLines));
    }

    /** Convertit un datetime `Y-m-d H:i:s` (UTC) en format iCal UTC `YYYYMMDDTHHmmssZ`. */
    private function toIcalUtc(?string $datetime): ?string
    {
        if ($datetime === null || $datetime === '') {
            return null;
        }
        $ts = strtotime($datetime);
        return $ts !== false ? gmdate('Ymd\THis\Z', $ts) : null;
    }

    private function escapeText(string $value): string
    {
        $value = str_replace('\\', '\\\\', $value);
        $value = str_replace(';', '\;', $value);
        $value = str_replace(',', '\,', $value);
        $value = str_replace("\n", '\n', $value);
        $value = str_replace("\r", '', $value);
        return $value;
    }

    /**
     * Replie les lignes dépassant 75 octets (RFC 5545 §3.1) en préservant
     * l'intégrité des séquences UTF-8 multibyte.
     */
    private function foldLine(string $line): string
    {
        if (strlen($line) <= 75) {
            return $line;
        }

        $folded = '';
        while (strlen($line) > 75) {
            $chunk = substr($line, 0, 75);
            // Reculer pour ne pas couper un octet de continuation UTF-8 (10xxxxxx)
            while (strlen($chunk) > 0 && (ord($chunk[-1]) & 0xC0) === 0x80) {
                $chunk = substr($chunk, 0, -1);
            }
            $folded .= $chunk . "\r\n ";
            $line    = substr($line, strlen($chunk));
        }
        return $folded . $line;
    }
}
