<?php
use kintai\UI\Components\Button;
use kintai\UI\Components\Badge;
use kintai\UI\Components\Table;
use kintai\UI\Components\Flash;

/** @var array  $shift_types */
/** @var array  $stores_map       id → name */
/** @var array  $type_store_names id de type => noms de stores triés (un type peut couvrir plusieurs stores) */
/** @var array  $type_store_ids   id de type => IDs de stores affectés */
/** @var array  $all_stores       stores visibles (scope du gestionnaire), pour les colonnes de la matrice */
/** @var string $sort */
$sort             ??= 'name_asc';
$stores_map       ??= [];
$type_store_names ??= [];
$type_store_ids   ??= [];
$all_stores       ??= [];

echo Flash::fromQuery('success', [
    'created'         => __('shift_type_created'),
    'updated'         => __('shift_type_updated'),
    'deleted'         => __('shift_type_deleted'),
    'store_enabled'   => __('shift_type_store_enabled'),
    'store_disabled'  => __('shift_type_store_disabled'),
])->render();

echo Flash::fromQuery('error', [
    'store_required' => __('val_store_required'),
])->render();
?>
<div class="page-header">
    <h2 class="page-header__title"><?= __('shift_types') ?> <span class="page-count">(<?= count($shift_types) ?>)</span></h2>
    <div class="page-header__actions">
        <?= Button::make('+ ' . __('new_type'))->primary()->link(route_url('admin.shift_types.create'))->render() ?>
    </div>
</div>

<div class="shift-types-layout">
    <div class="card shift-types-layout__matrix">
        <div class="card-header"><h3 class="card-title"><?= __('stores_plural') ?></h3></div>
        <div class="table-wrap">
            <table class="data-table matrix-table">
                <thead>
                    <tr>
                        <th><?= __('shift_types') ?></th>
                        <?php foreach ($all_stores as $s): ?>
                        <th class="td-center" title="<?= htmlspecialchars($s['name'] ?? '') ?>">
                            <?= htmlspecialchars($s['code'] ?? $s['name'] ?? '') ?>
                        </th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($shift_types === [] || $all_stores === []): ?>
                    <tr><td colspan="<?= count($all_stores) + 1 ?>" class="text-muted"><?= __('no_shift_type_found') ?></td></tr>
                    <?php endif; ?>
                    <?php foreach ($shift_types as $t): ?>
                    <?php $tid = (int) $t['id']; ?>
                    <tr>
                        <td class="td-nowrap">
                            <span class="color-swatch" style="background:<?= htmlspecialchars($t['color'] ?? '#ccc') ?>"></span>
                            <?= htmlspecialchars($t['name'] ?? '') ?>
                        </td>
                        <?php foreach ($all_stores as $s): ?>
                        <?php $sid = (int) $s['id']; ?>
                        <td class="td-center">
                            <form method="POST" action="<?= $BASE_URL ?>/admin/shift-types/<?= $tid ?>/toggle-store" class="form-inline">
                                <?= csrf_field() ?>
                                <input type="hidden" name="store_id" value="<?= $sid ?>">
                                <label class="form-toggle">
                                    <input type="checkbox" name="enabled" value="1" class="form-toggle__input"
                                           <?= in_array($sid, $type_store_ids[$tid] ?? [], true) ? 'checked' : '' ?>
                                           onchange="this.form.submit()">
                                    <span class="form-toggle__track"></span>
                                </label>
                            </form>
                        </td>
                        <?php endforeach; ?>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="card shift-types-layout__list">
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
        ?>
    </div>
</div>
