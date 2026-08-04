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
        $types = $managedIds === null
            ? $this->shiftTypes->findAll()
            : $this->shiftTypes->findByStores($managedIds);

        $storesMap = $this->buildStoresMap($managedIds);
        $typeStoreNames = $this->buildTypeStoreNames($types, $storesMap);

        $sort = $request->query('sort') ?? 'name_asc';
        usort($types, function ($a, $b) use ($sort, $typeStoreNames) {
            $storeA  = strtolower(implode(', ', $typeStoreNames[(int) $a['id']] ?? []));
            $storeB  = strtolower(implode(', ', $typeStoreNames[(int) $b['id']] ?? []));
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
            'title'            => 'Types de shifts',
            'shift_types'      => $types,
            'stores_map'       => $storesMap,
            'type_store_names' => $typeStoreNames,
            'sort'             => $sort,
        ], 'layout.app'));
    }

    public function createShiftType(Request $request): Response
    {
        return Response::html($this->view->render('scheduling.shift-types-form', [
            'title'              => 'Nouveau type de shift',
            'mode'               => 'create',
            'shift_type'         => [],
            'all_stores'         => $this->availableStores($this->managedIds($request)),
            'selected_store_ids' => [],
        ], 'layout.app'));
    }

    public function storeShiftType(Request $request): Response
    {
        $storeIds = $this->postedStoreIds($request);
        if ($storeIds === []) {
            return Response::redirect($this->base() . '/admin/shift-types/create?error=store_required');
        }
        $this->assertAnyStoreAccess($request, $storeIds);

        $savedType = $this->shiftTypes->save([
            'code'        => strtoupper(trim($request->post('code', ''))),
            'name'        => $request->post('name', ''),
            'start_time'  => $request->post('start_time', '08:00'),
            'end_time'    => $request->post('end_time', '16:00'),
            'color'       => $request->post('color', '#6366f1'),
            'hourly_rate' => $request->post('hourly_rate') !== '' ? (float) $request->post('hourly_rate') : null,
            'is_active'   => 1,
        ]);
        $this->shiftTypes->syncStores((int) $savedType['id'], $storeIds);

        $this->auditLogger->log($request, 'shift_type.created', 'shift_type', (int) ($savedType['id'] ?? 0), ['code' => $request->post('code', ''), 'name' => $request->post('name', '')], $storeIds[0]);

        return Response::redirect($this->base() . '/admin/shift-types?success=created');
    }

    public function editShiftType(Request $request): Response
    {
        $type = $this->shiftTypes->findById((int) $request->param('id'));
        if ($type === null) {
            throw new NotFoundException('Type de shift introuvable.');
        }
        $storeIds = $this->shiftTypes->getStoreIds((int) $type['id']);
        $this->assertAnyStoreAccess($request, $storeIds);

        return Response::html($this->view->render('scheduling.shift-types-form', [
            'title'              => 'Modifier ' . htmlspecialchars($type['name'] ?? ''),
            'mode'               => 'edit',
            'shift_type'         => $type,
            'all_stores'         => $this->availableStores($this->managedIds($request)),
            'selected_store_ids' => $storeIds,
        ], 'layout.app'));
    }

    public function updateShiftType(Request $request): Response
    {
        $type = $this->shiftTypes->findById((int) $request->param('id'));
        if ($type === null) {
            throw new NotFoundException('Type de shift introuvable.');
        }
        $existingStoreIds = $this->shiftTypes->getStoreIds((int) $type['id']);
        $this->assertAnyStoreAccess($request, $existingStoreIds);

        $submittedStoreIds = $this->postedStoreIds($request);
        if ($submittedStoreIds === []) {
            return Response::redirect($this->base() . '/admin/shift-types/' . $type['id'] . '/edit?error=store_required');
        }

        // Un gestionnaire de store ne voit dans son formulaire que les stores qu'il
        // gère (availableStores) : les affectations existantes à des stores hors de
        // sa portée ne doivent pas disparaître faute d'avoir pu être re-cochées.
        $managedIds = $this->managedIds($request);
        if ($managedIds !== null) {
            $visibleStoreIds  = array_map(fn($s) => (int) $s['id'], $this->availableStores($managedIds));
            $submittedStoreIds = array_values(array_intersect($submittedStoreIds, $visibleStoreIds));
            $outOfScopeStoreIds = array_values(array_diff($existingStoreIds, $visibleStoreIds));
            $submittedStoreIds = array_values(array_unique(array_merge($submittedStoreIds, $outOfScopeStoreIds)));
        }

        $newData = array_merge($type, [
            'code'        => strtoupper(trim($request->post('code', $type['code'] ?? ''))),
            'name'        => $request->post('name', $type['name'] ?? ''),
            'start_time'  => $request->post('start_time', $type['start_time'] ?? '08:00'),
            'end_time'    => $request->post('end_time', $type['end_time'] ?? '16:00'),
            'color'       => $request->post('color', $type['color'] ?? '#6366f1'),
            'hourly_rate' => $request->post('hourly_rate') !== '' ? (float) $request->post('hourly_rate') : null,
            'is_active'   => $request->post('is_active') === '1' ? 1 : 0,
        ]);

        $this->shiftTypes->save($newData);
        $this->shiftTypes->syncStores((int) $type['id'], $submittedStoreIds);

        $this->auditLogger->logUpdate($request, 'shift_type.updated', 'shift_type', (int) $type['id'], $type, $newData, [], $submittedStoreIds[0] ?? null);

        return Response::redirect($this->base() . '/admin/shift-types?success=updated');
    }

    public function deleteShiftType(Request $request): Response
    {
        $id   = (int) $request->param('id');
        $type = $this->shiftTypes->findById($id);
        $storeIds = $type !== null ? $this->shiftTypes->getStoreIds($id) : [];
        if ($type !== null) {
            $this->assertAnyStoreAccess($request, $storeIds);
        }
        $this->shiftTypes->delete($id);
        $this->auditLogger->log($request, 'shift_type.deleted', 'shift_type', $id, ['code' => $type['code'] ?? '', 'name' => $type['name'] ?? ''], $storeIds[0] ?? null);
        return Response::redirect($this->base() . '/admin/shift-types?success=deleted');
    }

    /** @return int[] */
    private function postedStoreIds(Request $request): array
    {
        $ids = $request->post('store_ids', []);
        if (!is_array($ids)) {
            return [];
        }
        return array_values(array_unique(array_map('intval', $ids)));
    }

    /** @return array<int, string[]> id de type => noms de stores triés */
    private function buildTypeStoreNames(array $types, array $storesMap): array
    {
        $map = [];
        foreach ($types as $t) {
            $id = (int) $t['id'];
            $names = array_values(array_filter(array_map(
                fn ($sid) => $storesMap[$sid] ?? null,
                $this->shiftTypes->getStoreIds($id),
            )));
            sort($names);
            $map[$id] = $names;
        }
        return $map;
    }
}
