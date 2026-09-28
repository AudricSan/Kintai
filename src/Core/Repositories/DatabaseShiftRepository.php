<?php

declare(strict_types=1);


namespace kintai\Core\Repositories;

use kintai\Domain\Eloquent\Shift as EloquentShift;
use kintai\Domain\Eloquent\ShiftDeletionLog as EloquentShiftDeletionLog;

final class DatabaseShiftRepository implements ShiftRepositoryInterface
{
    public function __construct() {}

    public function findById(int $id): ?array
    {
        $shift = EloquentShift::find($id);
        return $shift ? $shift->toArray() : null;
    }

    public function findByStore(int $storeId): array
    {
        return EloquentShift::where('store_id', $storeId)->get()->toArray();
    }

    public function findByUser(int $userId): array
    {
        return EloquentShift::where('user_id', $userId)->get()->toArray();
    }

    public function findByDate(int $storeId, string $date): array
    {
        return EloquentShift::where('store_id', $storeId)
            ->where('shift_date', $date)
            ->get()
            ->toArray();
    }

    public function findByUserAndDate(int $userId, int $storeId, string $date): array
    {
        return EloquentShift::where('user_id', $userId)
            ->where('store_id', $storeId)
            ->where('shift_date', $date)
            ->get()
            ->toArray();
    }

    public function findAll(): array
    {
        return EloquentShift::all()->toArray();
    }

    public function findAllByDate(string $date): array
    {
        return EloquentShift::where('shift_date', $date)->get()->toArray();
    }

    public function findOpen(?int $storeId = null): array
    {
        $query = EloquentShift::where('is_open', 1);
        if ($storeId !== null) {
            $query->where('store_id', $storeId);
        }
        return $query->get()->toArray();
    }

    public function save(array $data): array
    {
        if (!empty($data['id'])) {
            $shift = EloquentShift::findOrFail((int) $data['id']);
            $data['ical_sequence'] = ((int) ($shift->ical_sequence ?? 0)) + 1;
            if (!array_key_exists('updated_at', $data)) {
                $data['updated_at'] = date('Y-m-d H:i:s');
            }
            $shift->fill($data);
            $shift->save();
        } else {
            $data['ical_sequence'] = 0;
            $shift = EloquentShift::create($data);
        }
        return $shift->toArray();
    }

    public function delete(int $id): int
    {
        $shift = EloquentShift::find($id);
        if (!$shift) {
            return 0;
        }

        // Trace la suppression avant le hard delete, pour que le flux iCal de
        // l'employé puisse émettre un VEVENT CANCELLED (voir IcalController::feed()).
        EloquentShiftDeletionLog::create([
            'shift_id'         => $shift->id,
            'store_id'         => $shift->store_id,
            'user_id'          => $shift->user_id,
            'shift_date'       => $shift->shift_date,
            'start_time'       => $shift->start_time,
            'end_time'         => $shift->end_time,
            'cross_midnight'   => $shift->cross_midnight ?? 0,
            'shift_type_id'    => $shift->shift_type_id,
            'pause_minutes'    => $shift->pause_minutes ?? 0,
            'ical_sequence'    => ((int) ($shift->ical_sequence ?? 0)) + 1,
            'shift_created_at' => $shift->created_at,
            'deleted_at'       => date('Y-m-d H:i:s'),
        ]);

        return $shift->delete() ? 1 : 0;
    }

    public function findRecentDeletionsByUserAndStore(int $userId, int $storeId, string $since): array
    {
        return EloquentShiftDeletionLog::where('user_id', $userId)
            ->where('store_id', $storeId)
            ->where('deleted_at', '>=', $since)
            ->get()
            ->map(fn (EloquentShiftDeletionLog $log) => [
                'id'             => $log->shift_id,
                'shift_type_id'  => $log->shift_type_id,
                'shift_date'     => $log->shift_date,
                'start_time'     => $log->start_time,
                'end_time'       => $log->end_time,
                'cross_midnight' => $log->cross_midnight,
                'pause_minutes'  => $log->pause_minutes,
                'ical_sequence'  => $log->ical_sequence,
                'created_at'     => $log->shift_created_at,
                'updated_at'     => $log->deleted_at,
                'deleted_at'     => $log->deleted_at,
            ])
            ->all();
    }

    public function closeOpenShiftTo(int $id, int $userId): ?array
    {
        $shift = EloquentShift::find($id);
        if ($shift === null) {
            return null;
        }
        $affected = EloquentShift::where('id', $id)
            ->where('is_open', 1)
            ->update([
                'user_id'       => $userId,
                'is_open'       => 0,
                'ical_sequence' => ((int) $shift->ical_sequence) + 1,
                'updated_at'    => date('Y-m-d H:i:s'),
            ]);
        if ($affected === 0) {
            return null;
        }
        return EloquentShift::find($id)->toArray();
    }
}
