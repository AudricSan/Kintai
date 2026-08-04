<?php
use kintai\UI\Components\Button;
use kintai\UI\Components\Badge;
use kintai\UI\Components\Table;
use kintai\UI\Components\Flash;

/** @var array  $shift_types */
/** @var array  $stores_map       id → name */
/** @var array  $type_store_names id de type => noms de stores triés (un type peut couvrir plusieurs stores) */
/** @var string $sort */
$sort             ??= 'name_asc';
$stores_map       ??= [];
$type_store_names ??= [];

echo Flash::fromQuery('success', [
    'created' => __('shift_type_created'),
    'updated' => __('shift_type_updated'),
    'deleted' => __('shift_type_deleted'),
])->render();
?>
<div class="page-header">
    <h2 class="page-header__title"><?= __('shift_types') ?> <span class="page-count">(<?= count($shift_types) ?>)</span></h2>
    <div class="page-header__actions">
        <?= Button::make('+ ' . __('new_type'))->primary()->link(route_url('admin.shift_types.create'))->render() ?>
    </div>
</div>

<div class="card">
<?= Table::make()
    ->data($shift_types)
    ->emptyMessage(__('no_shift_type_found'))
    ->currentSort($sort)
    ->rowUrl(fn($t) => $BASE_URL . '/admin/shift-types/' . (int) $t['id'] . '/edit')
    ->column('#', fn($t) => (string) (int) $t['id'])
    ->sortable(__('stores_plural'), 'store', function ($t) use ($type_store_names) {
        $names = $type_store_names[(int) $t['id']] ?? [];
        return $names === []
            ? '<span class="text-muted">—</span>'
            : '<span class="text-sm-muted">' . htmlspecialchars(implode(', ', $names)) . '</span>';
    })
    ->sortable(__('code'), 'code', fn($t) => '<code class="code-sm">' . htmlspecialchars($t['code'] ?? '') . '</code>')
    ->sortable(__('name'), 'name', fn($t) => '<strong>' . htmlspecialchars($t['name'] ?? '') . '</strong>')
    ->column(__('start'), fn($t) => htmlspecialchars($t['start_time'] ?? ''))
    ->column(__('end'), fn($t) => htmlspecialchars($t['end_time'] ?? ''))
    ->column(__('color'), fn($t) =>
        '<span class="color-preview"><span class="color-swatch" style="background:' . htmlspecialchars($t['color'] ?? '#ccc') . '"></span><code class="color-code">' . htmlspecialchars($t['color'] ?? '') . '</code></span>'
    )
    ->sortable(__('status'), 'status', fn($t) =>
        !empty($t['is_active'])
            ? Badge::make(__('active'))->active()->render()
            : Badge::make(__('inactive'))->inactive()->render()
    )
    ->column(__('actions'), function($t) use ($BASE_URL) {
        $id = (int) $t['id'];
        $html = '<div class="btn-group">';
        $html .= '<form method="POST" action="' . htmlspecialchars($BASE_URL . '/admin/shift-types/' . $id . '/delete') . '" class="form-inline">';
        $html .= csrf_field();
        $html .= Button::make(__('delete'))->danger()->sm()->submit()->attrs(['onclick' => "return confirm('" . __('confirm_delete_shift_type') . "')"])->render();
        $html .= '</form>';
        $html .= '</div>';
        return $html;
    })
    ->render()
?></div>
