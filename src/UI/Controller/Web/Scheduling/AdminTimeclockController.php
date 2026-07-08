<?php

declare(strict_types=1);

namespace kintai\UI\Controller\Web\Scheduling;

use kintai\Core\Exceptions\NotFoundException;
use kintai\Core\Repositories\StoreRepositoryInterface;
use kintai\Core\Repositories\TimeclockRepositoryInterface;
use kintai\Core\Repositories\UserRepositoryInterface;
use kintai\Core\Request;
use kintai\Core\Response;
use kintai\Core\Services\AuditLogger;
use kintai\UI\Controller\Web\HasAdminAccess;
use kintai\UI\ViewRenderer;

final class AdminTimeclockController
{
    use HasAdminAccess;
    public function __construct(
        private readonly ViewRenderer $view,
        private readonly TimeclockRepositoryInterface $timeclocks,
        private readonly AuditLogger $auditLogger,
        private readonly StoreRepositoryInterface $stores,
        private readonly UserRepositoryInterface $users,
    ) {}

    public function timeclocks(Request $request): Response
    {
        $managedIds = $this->managedIds($request);

        $usersMap  = $this->buildUsersMap();
        $storesMap = $this->buildStoresMap($managedIds);

        $filterDate    = $request->query('date') ?? date('Y-m-d');
        $filterStoreId = ($request->query('store_id') ?: null);

        if ($filterStoreId !== null) {
            $entries = $this->timeclocks->findByStoreAndDate((int) $filterStoreId, $filterDate);
        } else {
            $entries = $this->filterByStore($this->timeclocks->findAll(), $managedIds);
            $entries = array_values(array_filter($entries, fn($e) => ($e['shift_date'] ?? '') === $filterDate));
        }

        usort($entries, fn($a, $b) => strcmp($a['clock_in_time'] ?? '', $b['clock_in_time'] ?? ''));

        return Response::html($this->view->render('scheduling.timeclocks', [
            'title'       => __('timeclocks'),
            'entries'     => $entries,
            'users_map'   => $usersMap,
            'stores_map'  => $storesMap,
            'filter_date' => $filterDate,
        ], 'layout.app'));
    }

    public function timeclocksEdit(Request $request): Response
    {
        $id = (int) $request->param('id');

        $entry = $this->timeclocks->findById($id);
        if ($entry === null) {
            throw new NotFoundException('Entrée de pointage introuvable.');
        }

        $clockIn  = trim((string) $request->post('clock_in_time',  ''));
        $clockOut = trim((string) $request->post('clock_out_time', ''));

        $updated = array_merge($entry, [
            'clock_in_time'  => $clockIn  !== '' ? $clockIn  : $entry['clock_in_time'],
            'clock_out_time' => $clockOut !== '' ? $clockOut : null,
            'updated_at'     => date('Y-m-d H:i:s'),
        ]);

        if (!empty($updated['clock_in_time']) && !empty($updated['clock_out_time'])) {
            $in       = new \DateTimeImmutable($updated['clock_in_time']);
            $out      = new \DateTimeImmutable($updated['clock_out_time']);
            $duration = (int) round(($out->getTimestamp() - $in->getTimestamp()) / 60);
            $updated['duration_minutes'] = max(0, $duration);
        } elseif (empty($updated['clock_out_time'])) {
            $updated['duration_minutes'] = null;
        }

        $this->timeclocks->save($updated);
        $this->auditLogger->logUpdate($request, 'timeclock.edited', 'timeclock', (int) $entry['id'], $entry, $updated, [], (int) ($entry['store_id'] ?? 0) ?: null);

        $date = $request->post('redirect_date', date('Y-m-d'));
        return Response::redirect($this->base() . '/admin/timeclocks?date=' . urlencode($date) . '&success=edited');
    }

    public function timeclocksDelete(Request $request): Response
    {
        $id = (int) $request->param('id');

        $entry = $this->timeclocks->findById($id);
        if ($entry === null) {
            throw new NotFoundException('Entrée de pointage introuvable.');
        }

        $storeId = (int) ($entry['store_id'] ?? 0);
        $this->auditLogger->log($request, 'timeclock.deleted', 'timeclock', $id, [
            'store_id' => $storeId ?: null,
            'date'     => $entry['clock_in_date'] ?? '',
        ], $storeId ?: null);

        $this->timeclocks->delete($id);

        $date = $request->query('date', date('Y-m-d'));
        return Response::redirect($this->base() . '/admin/timeclocks?date=' . urlencode($date) . '&success=deleted');
    }


}
