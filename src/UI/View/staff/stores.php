<?php
use kintai\UI\Components\Button;
use kintai\UI\Components\Badge;
use kintai\UI\Components\Table;
use kintai\UI\Components\Flash;

/** @var array  $stores */
/** @var string $sort */
$sort ??= 'name_asc';
$can = $user_can ?? fn(string $k): bool => true;

echo Flash::fromQuery('success', [
    'created' => __('store_created'),
    'updated' => __('store_updated'),
    'deleted' => __('store_deleted'),
])->render();
?>
<div class="page-header">
    <h2 class="page-header__title"><?= __('stores_plural') ?> <span class="page-count">(<?= count($stores) ?>)</span></h2>
    <div class="page-header__actions">
        <?= $can('stores.create') ? Button::make('+ ' . __('new_store'))->primary()->link(route_url('admin.stores.create'))->render() : '' ?>
    </div>
</div>

<div class="card">
<?= Table::make()
    ->data($stores)
    ->emptyMessage(__('no_store_found'))
    ->currentSort($sort)
    ->rowUrl(fn($s) => $BASE_URL . '/admin/stores/' . (int) $s['id'] . '/edit')
    ->column('#', fn($s) => (string) (int) $s['id'])
    ->sortable(__('code'), 'code', fn($s) => '<code class="code-sm">' . htmlspecialchars($s['code'] ?? '') . '</code>')
    ->sortable(__('name'), 'name', fn($s) => '<strong>' . htmlspecialchars($s['name'] ?? '') . '</strong>')
    ->sortable(__('type'), 'type', fn($s) => htmlspecialchars($s['type'] ?? ''))
    ->column(__('timezone'), fn($s) => '<span class="text-sm-muted">' . htmlspecialchars($s['timezone'] ?? '') . '</span>')
    ->column(__('currency'), fn($s) => htmlspecialchars($s['currency'] ?? ''))
    ->sortable(__('status'), 'status', fn($s) =>
        (!empty($s['is_active']) && empty($s['deleted_at']))
            ? Badge::make(__('active'))->active()->render()
            : Badge::make(__('inactive'))->inactive()->render()
    )
    ->column(__('actions'), function($s) use ($BASE_URL, $can) {
        $id = (int) $s['id'];
        $html = '<div class="btn-group">';
        if ($can('payroll.view')) {
            $html .= Button::make('📊')->ghost()->sm()->link($BASE_URL . '/admin/stores/' . $id . '/stats')->attrs(['title' => __('statistics')])->render();
            $html .= Button::make('👥')->ghost()->sm()->link($BASE_URL . '/admin/stores/' . $id . '/employee-report')->attrs(['title' => __('employee_report')])->render();
        }
        if ($can('stores.delete')) {
            $html .= '<form method="POST" action="' . htmlspecialchars($BASE_URL . '/admin/stores/' . $id . '/delete') . '" class="form-inline" onsubmit="return confirm(\'' . __('confirm_delete_store') . '\')">';
            $html .= csrf_field();
            $html .= Button::make(__('delete'))->danger()->sm()->submit()->render();
            $html .= '</form>';
        }
        $html .= '</div>';
        return $html;
    })
    ->render()
?></div>
