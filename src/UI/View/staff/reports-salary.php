<?php

declare(strict_types=1);

use kintai\UI\Components\Badge;
use kintai\UI\Components\Button;
use kintai\UI\Components\Flash;
use kintai\UI\Components\Table;

/**
 * @var array       $store
 * @var array       $reports
 * @var string      $BASE_URL
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

$storeNames = [];
foreach ($stores as $s) {
    $storeNames[(int) $s['id']] = $s['name'] ?? '#' . $s['id'];
}

$allMode = !empty($stores); // mode "tous les magasins"

if ($allMode) {
    $baseList = $BASE_URL . '/admin/reports/salary';
    $storeId = 0;
} else {
    $storeId = (int) $store['id'];
    $storeNames[$storeId] = $store['name'] ?? '';
    $baseList = $BASE_URL . '/admin/stores/' . $storeId . '/reports/salary';
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
    'created' => __('sr_created'),
    'updated' => __('sr_updated'),
    'deleted' => __('sr_deleted'),
])->render();
?>

<div class="page-header">
    <h2 class="page-header__title">
        <?= __('sr_title') ?><?php if (!$allMode): ?> — <?= htmlspecialchars($store['name'] ?? '') ?><?php endif; ?>
    </h2>
    <div class="page-header__actions">
        <?php if (!$allMode): ?>
        <?= Button::make('+ ' . __('sr_new'))->primary()->link($baseList . '/create')->render() ?>
        <?= Button::make('← ' . __('back'))->ghost()->sm()->link($BASE_URL . '/admin/stores/' . $storeId . '/edit')->render() ?>
        <?php endif; ?>
    </div>
</div>

<!-- Barre de filtres -->
<div class="card card--filters mb-sm">
    <form method="GET" action="<?= $allMode ? $BASE_URL . '/admin/reports/salary' : $baseList ?>" class="filter-bar">
        <div class="shifts-filters__row">

            <?php if ($allMode): ?>
            <div class="shifts-filters__group">
                <label class="shifts-filters__label" for="sf-store"><?= __('store') ?></label>
                <select id="sf-store" name="store_id" class="form-control form-control--sm" onchange="this.form.submit()">
                    <option value="0"><?= __('all_stores') ?></option>
                    <?php foreach ($stores as $s): ?>
                        <option value="<?= (int) $s['id'] ?>" <?= (int) $s['id'] === $filter_store_id ? 'selected' : '' ?>><?= htmlspecialchars($s['name'] ?? '') ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>

            <div class="shifts-filters__group">
                <label class="shifts-filters__label" for="sf-year"><?= __('year') ?></label>
                <select id="sf-year" name="year" class="form-control form-control--sm" onchange="this.form.submit()">
                    <option value=""><?= __('all_years') ?></option>
                    <?php foreach ($years as $y): ?>
                        <option value="<?= $y ?>" <?= (string) $y === $filter_year ? 'selected' : '' ?>><?= $y ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="shifts-filters__group">
                <label class="shifts-filters__label" for="sf-month"><?= __('month') ?></label>
                <select id="sf-month" name="month" class="form-control form-control--sm" onchange="this.form.submit()">
                    <option value=""><?= __('all_months') ?></option>
                    <?php foreach ($months as $val => $label): ?>
                        <option value="<?= $val ?>" <?= $val === $filter_month ? 'selected' : '' ?>><?= $label ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="shifts-filters__group">
                <label class="shifts-filters__label" for="sf-person"><?= __('sr_person_in_charge') ?></label>
                <input type="text" id="sf-person" name="person" class="form-control form-control--sm" value="<?= htmlspecialchars($filter_person) ?>">
            </div>

            <div class="shifts-filters__actions">
                <?= Button::make(__('filter'))->ghost()->sm()->submit()->render() ?>
                <?php if ($filter_year !== '' || $filter_month !== '' || $filter_person !== '' || $filter_store_id > 0): ?>
                    <a href="<?= $allMode ? $BASE_URL . '/admin/reports/salary' : $baseList ?>" class="btn btn--ghost btn--sm"><?= __('reset') ?></a>
                <?php endif; ?>
            </div>
        </div>
    </form>
</div>

<div class="card">
<?php if (empty($reports)): ?>
    <div class="empty-state"><?= __('sr_empty') ?></div>
<?php else:
    $tbl = Table::make()
        ->data($reports)
        ->emptyMessage(__('sr_empty'))
        ->rowUrl(fn($r) => $BASE_URL . '/admin/stores/' . (int) ($r['store_id'] ?? 0) . '/reports/salary/' . (int) $r['id']);
    if ($allMode):
        $tbl->column(__('store'), fn($r) => htmlspecialchars($storeNames[(int) ($r['store_id'] ?? 0)] ?? '—'));
    endif;
    echo $tbl
        ->currentSort($sort ?? 'target_month_desc')
        ->sortable(__('sr_target_month'), 'target_month', fn($r) => htmlspecialchars($r['target_month'] ?? ''))
        ->sortable(__('sr_person_in_charge'), 'person_in_charge', fn($r) => htmlspecialchars($r['person_in_charge'] ?? '—'))
        ->sortable(__('sr_total_payment'), 'total_payment', fn($r) => '<span class="td-mono">' . format_currency((float) ($r['total_payment'] ?? 0), 'JPY') . '</span>', 'td-right')
        ->sortable(__('sr_net_payment'), 'net_payment', fn($r) => '<span class="td-mono">' . format_currency((float) ($r['net_payment'] ?? 0), 'JPY') . '</span>', 'td-right')
        ->column(__('sr_active_employees'), fn($r) => '<span class="td-mono">' . ((int) ($r['active_employees'] ?? 0)) . '</span>', 'td-right')
        ->column(__('actions'), function($r) use ($BASE_URL) {
            $rid = (int) $r['id'];
            $sid = (int) ($r['store_id'] ?? 0);
            $base = $BASE_URL . '/admin/stores/' . $sid . '/reports/salary/' . $rid;
            $html = '<div class="btn-group">';
            $html .= Button::make(__('view'))->ghost()->sm()->link($base)->render();
            $html .= Button::make(__('edit'))->ghost()->sm()->link($base . '/edit')->render();
            $html .= '<form method="POST" action="' . htmlspecialchars($base . '/delete') . '" class="form-inline" onsubmit="return confirm(\'' . __('sr_confirm_delete') . '\')">';
            $html .= csrf_field();
            $html .= Button::make(__('delete'))->danger()->sm()->submit()->render();
            $html .= '</form>';
            $html .= Button::make(__('sr_pdf'))->ghost()->sm()->link($base . '/pdf')->render();
            $html .= '</div>';
            return $html;
        })
        ->render();
endif; ?>
</div>
