<?php
use kintai\UI\Components\Badge;
use kintai\UI\Components\Button;
use kintai\UI\Components\Flash;
use kintai\UI\Components\Table;

/**
 * @var array $store
 * @var array $reports
 * @var array $authUser
 */
$storeId = (int) $store['id'];
$base = $BASE_URL . '/admin/stores/' . $storeId . '/reports/hiring';

echo Flash::fromQuery('success', [
    'created' => __('operation_success'),
    'updated' => __('operation_success'),
    'deleted' => __('operation_success'),
])->render();
?>
<div class="page-header">
    <h2 class="page-header__title"><?= __('hiring_reports') ?> <span class="page-count">(<?= count($reports) ?>)</span></h2>
    <div class="page-header__actions">
        <?= Button::make('+ ' . __('new_hiring_report'))->primary()->link($base . '/create')->render() ?>
        <?= Button::make(__('back'))->ghost()->link($BASE_URL . '/admin/stores/' . $storeId . '/edit')->render() ?>
    </div>
</div>

<div class="card">
<?php if (empty($reports)): ?>
    <div class="card-body">
        <p class="text-muted"><?= __('no_hiring_reports') ?></p>
    </div>
<?php else: ?>
    <?= Table::make()
        ->data($reports)
        ->emptyMessage(__('none'))
        ->rowUrl(fn($r) => $BASE_URL . '/admin/stores/' . $storeId . '/reports/hiring/' . (int) $r['id'])
        ->column(__('employee_number'), fn($r) => '<code>' . htmlspecialchars($r['employee_number'] ?? '') . '</code>')
        ->column(__('employee_name'), fn($r) => '<strong>' . htmlspecialchars($r['employee_name'] ?? '') . '</strong>')
        ->column(__('hire_date'), fn($r) => htmlspecialchars($r['hire_date'] ?? '—'))
        ->column(__('store'), fn($r) => htmlspecialchars($r['store_name'] ?? $store['name'] ?? ''))
        ->column(__('hired_by'), fn($r) => htmlspecialchars($r['hired_by'] ?? '—'))
        ->column(__('created_at'), fn($r) => '<span class="td-date-muted">' . htmlspecialchars(substr($r['created_at'] ?? '', 0, 10)) . '</span>')
        ->column(__('actions'), function($r) use ($base, $authUser) {
            $rid = (int) $r['id'];
            $html = '<div class="btn-group">';
            $html .= Button::make('PDF')->ghost()->sm()->link($base . '/' . $rid . '/pdf')->render();
            if (!empty($authUser['is_admin'])) {
                $html .= '<form method="POST" action="' . htmlspecialchars($base . '/' . $rid . '/delete') . '" class="form-inline" onsubmit="return confirm(\'' . __('confirm_delete_hiring_report') . '\')">';
                $html .= csrf_field();
                $html .= Button::make(__('delete'))->danger()->sm()->submit()->render();
                $html .= '</form>';
            }
            $html .= '</div>';
            return $html;
        })
        ->render()
    ?>
<?php endif; ?>
</div>
