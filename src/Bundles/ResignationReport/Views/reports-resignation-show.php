<?php
use kintai\UI\Components\Button;

/**
 * @var array $store
 * @var array $report
 */

$storeId = (int) $store['id'];
$reportId = (int) $report['id'];
$base = $BASE_URL . '/admin/stores/' . $storeId . '/reports/resignation';
?>
<div class="page-header">
    <h2 class="page-header__title"><?= __('resignation_report') ?> — <?= htmlspecialchars($report['employee_name'] ?? '') ?></h2>
    <div class="page-header__actions">
        <?= Button::make('← ' . __('back'))->ghost()->sm()->link($base)->render() ?>
        <?= Button::make(__('edit'))->primary()->sm()->link($base . '/' . $reportId . '/edit')->render() ?>
        <?= Button::make('PDF')->ghost()->sm()->link($base . '/' . $reportId . '/pdf')->attrs(['target' => '_blank'])->render() ?>
        <form method="POST" action="<?= $base . '/' . $reportId . '/delete' ?>" class="form-inline" onsubmit="return confirm('<?= __('confirm_delete_resignation_report') ?>')">
            <?= csrf_field() ?>
            <?= Button::make(__('delete'))->danger()->sm()->submit()->render() ?>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <table class="detail-table">
            <tr>
                <th><?= __('employee_number') ?></th>
                <td><?= htmlspecialchars($report['employee_number'] ?? '—') ?></td>
            </tr>
            <tr>
                <th><?= __('employee_name') ?></th>
                <td><?= htmlspecialchars($report['employee_name'] ?? '—') ?></td>
            </tr>
            <tr>
                <th><?= __('resignation_date') ?></th>
                <td><?= htmlspecialchars($report['resignation_date'] ?? '—') ?></td>
            </tr>
            <tr>
                <th><?= __('reason') ?></th>
                <td><?= nl2br(htmlspecialchars($report['reason'] ?? '—')) ?></td>
            </tr>
            <tr>
                <th><?= __('resignation_notice') ?></th>
                <td><?= nl2br(htmlspecialchars($report['resignation_notice'] ?? '—')) ?></td>
            </tr>
            <tr>
                <th><?= __('notes') ?></th>
                <td><?= nl2br(htmlspecialchars($report['notes'] ?? '—')) ?></td>
            </tr>
            <tr>
                <th><?= __('person_in_charge') ?></th>
                <td><?= htmlspecialchars($report['person_in_charge'] ?? '—') ?></td>
            </tr>
        </table>
    </div>
</div>
