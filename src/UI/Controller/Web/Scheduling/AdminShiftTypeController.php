<?php

declare(strict_types=1);

namespace kintai\UI\Controller\Web\Scheduling;

use kintai\Core\Exceptions\ForbiddenException;
use kintai\Core\Exceptions\NotFoundException;
use kintai\Core\Repositories\ShiftTypeRepositoryInterface;
use kintai\Core\Repositories\StoreRepositoryInterface;
use kintai\Core\Request;
use kintai\Core\Response;
use kintai\Core\Services\AuditLogger;
use kintai\UI\Controller\Web\HasAdminAccess;
use kintai\UI\ViewRenderer;

final class AdminShiftTypeController
{
    use HasAdminAccess;
    public function __construct(
        private readonly ViewRenderer $view,
        private readonly ShiftTypeRepositoryInterface $shiftTypes,
        private readonly StoreRepositoryInterface $stores,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function shiftTypes(Request $request): Response
    {
        $managedIds = $this->managedIds($request);
        $types = $this->filterByStore($this->shiftTypes->findAll(), $managedIds);

        $storesMap = $this->buildStoresMap($managedIds);

        $sort = $request->query('sort') ?? 'name_asc';
        usort($types, function ($a, $b) use ($sort, $storesMap) {
            $storeA  = strtolower($storesMap[(int)($a['store_id'] ?? 0)] ?? '');
            $storeB  = strtolower($storesMap[(int)($b['store_id'] ?? 0)] ?? '');
            $codeA   = strtolower($a['code'] ?? '');
            $codeB   = strtolower($b['code'] ?? '');
            $nameA   = strtolower($a['name'] ?? '');
            $nameB   = strtolower($b['name'] ?? '');
            $statA   = (int) !empty($a['is_active']);
            $statB   = (int) !empty($b['is_active']);
            return match ($sort) {
                'name_desc'    => strcmp($nameB, $nameA),
                'store_asc'    => strcmp($storeA, $storeB) ?: strcmp($nameA, $nameB),
                'store_desc'   => strcmp($storeB, $storeA) ?: strcmp($nameA, $nameB),
                'code_asc'     => strcmp($codeA, $codeB) ?: strcmp($nameA, $nameB),
                'code_desc'    => strcmp($codeB, $codeA) ?: strcmp($nameA, $nameB),
                'status_asc'   => $statA <=> $statB ?: strcmp($nameA, $nameB),
                'status_desc'  => $statB <=> $statA ?: strcmp($nameA, $nameB),
                default        => strcmp($nameA, $nameB),
            };
        });

        return Response::html($this->view->render('scheduling.shift-types', [
            'title'       => 'Types de shifts',
            'shift_types' => $types,
            'stores_map'  => $storesMap,
            'sort'        => $sort,
        ], 'layout.app'));
    }

    public function createShiftType(Request $request): Response
    {
        return Response::html($this->view->render('scheduling.shift-types-form', [
            'title'      => 'Nouveau type de shift',
            'mode'       => 'create',
            'shift_type' => [],
            'all_stores' => $this->availableStores($this->managedIds($request)),
        ], 'layout.app'));
    }

    public function storeShiftType(Request $request): Response
    {
        $storeId = (int) $request->post('store_id', 0);
        $this->assertStoreAccess($request, $storeId);

        $savedType =         $savedType = $this->shiftTypes->save([
            'store_id'    => $storeId,
            'code'        => strtoupper(trim($request->post('code', ''))),
            'name'        => $request->post('name', ''),
            'start_time'  => $request->post('start_time', '08:00'),
            'end_time'    => $request->post('end_time', '16:00'),
            'color'       => $request->post('color', '#6366f1'),
            'hourly_rate' => $request->post('hourly_rate') !== '' ? (float) $request->post('hourly_rate') : null,
            'is_active'   => 1,
        ]);

        $this->auditLogger->log($request, 'shift_type.created', 'shift_type', (int) ($savedType['id'] ?? 0), ['code' => $request->post('code', ''), 'name' => $request->post('name', '')], $storeId);

        return Response::redirect($this->base() . '/admin/shift-types?success=created');
    }

    public function editShiftType(Request $request): Response
    {
        $type = $this->shiftTypes->findById((int) $request->param('id'));
        if ($type === null) {
            throw new NotFoundException('Type de shift introuvable.');
        }
        $this->assertStoreAccess($request, (int) $type['store_id']);

        return Response::html($this->view->render('scheduling.shift-types-form', [
            'title'      => 'Modifier ' . htmlspecialchars($type['name'] ?? ''),
            'mode'       => 'edit',
            'shift_type' => $type,
            'all_stores' => $this->availableStores($this->managedIds($request)),
        ], 'layout.app'));
    }

    public function updateShiftType(Request $request): Response
    {
        $type = $this->shiftTypes->findById((int) $request->param('id'));
        if ($type === null) {
            throw new NotFoundException('Type de shift introuvable.');
        }
        $this->assertStoreAccess($request, (int) $type['store_id']);

        $newData = array_merge($type, [
            'store_id'    => (int) $request->post('store_id', $type['store_id'] ?? 0),
            'code'        => strtoupper(trim($request->post('code', $type['code'] ?? ''))),
            'name'        => $request->post('name', $type['name'] ?? ''),
            'start_time'  => $request->post('start_time', $type['start_time'] ?? '08:00'),
            'end_time'    => $request->post('end_time', $type['end_time'] ?? '16:00'),
            'color'       => $request->post('color', $type['color'] ?? '#6366f1'),
            'hourly_rate' => $request->post('hourly_rate') !== '' ? (float) $request->post('hourly_rate') : null,
            'is_active'   => $request->post('is_active') === '1' ? 1 : 0,
        ]);

        $this->shiftTypes->save($newData);

        $this->auditLogger->logUpdate($request, 'shift_type.updated', 'shift_type', (int) $type['id'], $type, $newData, [], (int) ($type['store_id'] ?? 0));

        return Response::redirect($this->base() . '/admin/shift-types?success=updated');
    }

    public function deleteShiftType(Request $request): Response
    {
        $id   = (int) $request->param('id');
        $type = $this->shiftTypes->findById($id);
        if ($type !== null) {
            $this->assertStoreAccess($request, (int) $type['store_id']);
        }
        $this->shiftTypes->delete($id);
        $this->auditLogger->log($request, 'shift_type.deleted', 'shift_type', $id, ['code' => $type['code'] ?? '', 'name' => $type['name'] ?? ''], $type ? (int) $type['store_id'] : null);
        return Response::redirect($this->base() . '/admin/shift-types?success=deleted');
    }


}
