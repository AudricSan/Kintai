<?php
use kintai\UI\Components\Button;
use kintai\UI\Components\Flash;
use kintai\UI\Components\Table;

/**
 * @var array       $store
 * @var array       $reports
 * @var array|null  $stores
 * @var int         $filter_store_id
 */

$stores ??= [];
$filter_store_id ??= 0;

// Construire une map store_id → nom
$storeNames = [];
foreach ($stores as $s) {
    $storeNames[(int) $s['id']] = $s['name'] ?? '#' . $s['id'];
}

if (!empty($stores)) {
    // Mode "tous les magasins" (owner)
    $base = $BASE_URL . '/admin/reports/resignation';
    $storeId = 0;
    // La création n'est possible qu'une fois un magasin précis choisi via le
    // filtre — sans quoi on ne sait pas pour quel magasin créer le rapport.
    $createBase = $filter_store_id > 0 ? $BASE_URL . '/admin/stores/' . $filter_store_id . '/reports/resignation' : null;
} else {
    $storeId = (int) $store['id'];
    $storeNames[$storeId] = $store['name'] ?? '';
    $base = $BASE_URL . '/admin/stores/' . $storeId . '/reports/resignation';
    $createBase = $base;
}

echo Flash::fromQuery('success', [
    'created' => __('operation_success'),
    'updated' => __('operation_success'),
    'deleted' => __('operation_success'),
])->render();

if (!empty($stores)): ?>
<form method="GET" action="<?= $BASE_URL ?>/admin/reports/resignation" class="form-flex" style="margin-bottom:1rem">
    <div class="form-group form-group--200">
        <select name="store_id" class="form-control" onchange="this.form.submit()">
            <option value="0"><?= __('all_stores') ?></option>
            <?php foreach ($stores as $s): ?>
                <option value="<?= (int) $s['id'] ?>" <?= (int) $s['id'] === $filter_store_id ? 'selected' : '' ?>><?= htmlspecialchars($s['name'] ?? '') ?></option>
            <?php endforeach; ?>
        </select>
    </div>
</form>
<?php endif; ?>

<div class="page-header">
    <h2 class="page-header__title"><?= __('resignation_reports') ?> <span class="page-count">(<?= count($reports) ?>)</span></h2>
    <div class="page-header__actions">
        <?php if ($createBase !== null): ?>
        <?= Button::make('+ ' . __('new_resignation_report'))->primary()->link($createBase . '/create')->render() ?>
        <?php endif; ?>
        <?php if ($storeId > 0): ?>
        <?= Button::make(__('back'))->ghost()->link($BASE_URL . '/admin/stores/' . $storeId . '/edit')->render() ?>
        <?php endif; ?>
    </div>
</div>

<div class="card">
<?php
$tbl = Table::make()
    ->data($reports)
    ->emptyMessage(__('none'))
    ->rowUrl(fn($r) => $BASE_URL . '/admin/stores/' . (int) ($r['store_id'] ?? 0) . '/reports/resignation/' . (int) $r['id']);
if (!empty($stores)):
    $tbl->column(__('store'), fn($r) => htmlspecialchars($storeNames[(int) ($r['store_id'] ?? 0)] ?? '—'));
endif;
echo $tbl
    ->column(__('employee_number'), fn($r) => htmlspecialchars($r['employee_number'] ?? '—'))
    ->column(__('employee_name'), fn($r) => htmlspecialchars($r['employee_name'] ?? '—'))
    ->column(__('resignation_date'), fn($r) => htmlspecialchars($r['resignation_date'] ?? '—'))
    ->column(__('person_in_charge'), fn($r) => htmlspecialchars($r['person_in_charge'] ?? '—'))
    ->column(__('actions'), function($r) use ($BASE_URL) {
        $rid = (int) $r['id'];
        $sid = (int) ($r['store_id'] ?? 0);
        $baseStore = $BASE_URL . '/admin/stores/' . $sid . '/reports/resignation';
        $html = '<div class="btn-group">';
        $html .= '<a href="' . $baseStore . '/' . $rid . '" class="btn btn--ghost btn--sm">' . __('details') . '</a>';
        $html .= '<a href="' . $baseStore . '/' . $rid . '/edit" class="btn btn--ghost btn--sm">' . __('edit') . '</a>';
        if (!empty($r['user_id'])) {
            $html .= '<form method="POST" action="' . $baseStore . '/' . $rid . '/reactivate" class="form-inline">' . csrf_field()
                . '<button type="submit" class="btn btn--warning btn--sm" onclick="return confirm(\'' . __('confirm_reactivate') . '\')">' . __('reactivate') . '</button></form>';
        }
        $html .= '<form method="POST" action="' . $baseStore . '/' . $rid . '/delete" class="form-inline">' . csrf_field()
            . '<button type="submit" class="btn btn--danger btn--sm" onclick="return confirm(\'' . __('confirm_delete_resignation_report') . '\')">' . __('delete') . '</button></form>';
        $html .= '<a href="' . $baseStore . '/' . $rid . '/pdf" class="btn btn--ghost btn--sm" title="PDF">⎙</a>';
        $html .= '</div>';
        return $html;
    })
    ->render();
?></div>
