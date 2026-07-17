<?php
use kintai\UI\Components\Button;
use kintai\UI\Components\Flash;
use kintai\UI\Components\Table;

/**
 * @var array       $store
 * @var array       $reports
 * @var array|null  $stores
 * @var int         $filter_store_id
 * @var string      $filter_year
 * @var string      $filter_month
 * @var string      $filter_person
 */

$stores ??= [];
$filter_store_id ??= 0;
$filter_year ??= '';
$filter_month ??= '';
$filter_person ??= '';

// Construire une map store_id → nom
$storeNames = [];
foreach ($stores as $s) {
    $storeNames[(int) $s['id']] = $s['name'] ?? '#' . $s['id'];
}

$allMode = !empty($stores); // mode "tous les magasins"

if ($allMode) {
    $baseList = $BASE_URL . '/admin/reports/resignation';
    $storeId = 0;
    // En vue "tous les magasins", la création n'est possible qu'une fois un
    // magasin précis choisi via le filtre — sans quoi on ne sait pas pour
    // quel magasin créer le rapport.
    $createBase = $filter_store_id > 0 ? $BASE_URL . '/admin/stores/' . $filter_store_id . '/reports/resignation' : null;
} else {
    $storeId = (int) $store['id'];
    $storeNames[$storeId] = $store['name'] ?? '';
    $baseList = $BASE_URL . '/admin/stores/' . $storeId . '/reports/resignation';
    $createBase = $baseList;
}

// Générer la liste des années disponibles (2020 à année courante +1)
$currentYear = (int) date('Y');
$years = range(2020, $currentYear + 1);
$months = [
    '01' => __('January'), '02' => __('February'), '03' => __('March'),
    '04' => __('April'), '05' => __('May'), '06' => __('June'),
    '07' => __('July'), '08' => __('August'), '09' => __('September'),
    '10' => __('October'), '11' => __('November'), '12' => __('December'),
];

echo Flash::fromQuery('success', [
    'created' => __('operation_success'),
    'updated' => __('operation_success'),
    'deleted' => __('operation_success'),
])->render();
?>

<div class="page-header">
    <h2 class="page-header__title">
        <?= __('resignation_reports') ?> <span class="page-count">(<?= count($reports) ?>)</span>
    </h2>
    <div class="page-header__actions">
        <?php if ($allMode):
            $exportQuery = array_filter([
                'store_id' => $filter_store_id ?: null,
                'year'     => $filter_year ?: null,
                'month'    => $filter_month ?: null,
                'person'   => $filter_person ?: null,
            ]);
            $exportQueryString = $exportQuery ? '?' . http_build_query($exportQuery) : '';
        ?>
        <?= Button::make('PDF')->ghost()->sm()->link($BASE_URL . '/admin/reports/resignation/export/pdf' . $exportQueryString)->render() ?>
        <?= Button::make('JSON')->ghost()->sm()->link($BASE_URL . '/admin/reports/resignation/export/json' . $exportQueryString)->render() ?>
        <?php endif; ?>
        <?php if ($createBase !== null): ?>
        <?= Button::make('+ ' . __('new_resignation_report'))->primary()->link($createBase . '/create')->render() ?>
        <?php elseif ($allMode): ?>
        <details class="store-picker">
            <summary class="btn btn--primary">+ <?= __('new_resignation_report') ?></summary>
            <div class="store-picker__panel">
                <div class="store-picker__title"><?= __('store_picker_choose') ?></div>
                <?php foreach ($stores as $s): ?>
                    <a class="store-picker__item" href="<?= htmlspecialchars($BASE_URL . '/admin/stores/' . (int) $s['id'] . '/reports/resignation/create') ?>"><?= htmlspecialchars($s['name'] ?? '') ?></a>
                <?php endforeach; ?>
            </div>
        </details>
        <?php endif; ?>
        <?php if (!$allMode): ?>
        <?= Button::make('← ' . __('back'))->ghost()->sm()->link($BASE_URL . '/admin/stores/' . $storeId . '/edit')->render() ?>
        <?php endif; ?>
    </div>
</div>

<!-- Barre de filtres -->
<div class="card card--filters mb-sm">
    <form method="GET" action="<?= $allMode ? $BASE_URL . '/admin/reports/resignation' : $baseList ?>" class="filter-bar">
        <div class="shifts-filters__row">

            <?php if ($allMode): ?>
            <div class="shifts-filters__group">
                <label class="shifts-filters__label" for="rf-store"><?= __('store') ?></label>
                <select id="rf-store" name="store_id" class="form-control form-control-sm" onchange="this.form.submit()">
                    <option value="0"><?= __('all_stores') ?></option>
                    <?php foreach ($stores as $s): ?>
                        <option value="<?= (int) $s['id'] ?>" <?= (int) $s['id'] === $filter_store_id ? 'selected' : '' ?>><?= htmlspecialchars($s['name'] ?? '') ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>

            <div class="shifts-filters__group">
                <label class="shifts-filters__label" for="rf-year"><?= __('year') ?></label>
                <select id="rf-year" name="year" class="form-control form-control-sm" onchange="this.form.submit()">
                    <option value=""><?= __('all_years') ?></option>
                    <?php foreach ($years as $y): ?>
                        <option value="<?= $y ?>" <?= (string) $y === $filter_year ? 'selected' : '' ?>><?= $y ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="shifts-filters__group">
                <label class="shifts-filters__label" for="rf-month"><?= __('month') ?></label>
                <select id="rf-month" name="month" class="form-control form-control-sm" onchange="this.form.submit()">
                    <option value=""><?= __('all_months') ?></option>
                    <?php foreach ($months as $val => $label): ?>
                        <option value="<?= $val ?>" <?= $val === $filter_month ? 'selected' : '' ?>><?= $label ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="shifts-filters__group">
                <label class="shifts-filters__label" for="rf-person"><?= __('person_in_charge') ?></label>
                <input type="text" id="rf-person" name="person" class="form-control form-control-sm" value="<?= htmlspecialchars($filter_person) ?>">
            </div>

            <div class="shifts-filters__actions">
                <?php if ($filter_year !== '' || $filter_month !== '' || $filter_person !== '' || $filter_store_id > 0): ?>
                    <a href="<?= $allMode ? $BASE_URL . '/admin/reports/resignation' : $baseList ?>" class="btn btn--ghost btn--sm"><?= __('reset') ?></a>
                <?php endif; ?>
            </div>
        </div>
    </form>
</div>

<div class="card">
<?php
$tbl = Table::make()
    ->data($reports)
    ->emptyMessage(__('none'))
    ->rowUrl(fn($r) => $BASE_URL . '/admin/stores/' . (int) ($r['store_id'] ?? 0) . '/reports/resignation/' . (int) $r['id']);
if ($allMode):
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
