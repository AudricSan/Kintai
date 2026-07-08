<?php
/**
 * @var array  $store
 * @var array  $report
 * @var array|null $author
 * @var array|null $validator
 * @var array  $settings
 * @var array  $authUser
 * @var array|null $membership
 * @var \kintai\Core\Services\DailyReportPermissionService $permissions
 * @var bool   $canEdit
 * @var bool   $canSubmit
 * @var bool   $canValidate
 * @var bool   $canSendMail
 * @var bool   $canDelete
 * @var string $BASE_URL
 */
$storeId  = (int) $store['id'];
$reportId = (int) $report['id'];
$currency = currency_symbol($store['currency'] ?? 'JPY');

$statusLabels = [
    'draft'     => ['label' => __('dr_status_draft'),     'class' => 'badge--secondary'],
    'submitted' => ['label' => __('dr_status_submitted'), 'class' => 'badge--warning'],
    'validated' => ['label' => __('dr_status_validated'), 'class' => 'badge--active'],
];
$st    = $report['status'] ?? 'draft';
$badge = $statusLabels[$st] ?? ['label' => $st, 'class' => 'badge--secondary'];

$authorName    = trim(($author['last_name'] ?? '') . ' ' . ($author['first_name'] ?? '')) ?: ($author['email'] ?? '—');
$validatorName = $validator
    ? (trim(($validator['last_name'] ?? '') . ' ' . ($validator['first_name'] ?? '')) ?: ($validator['email'] ?? '—'))
    : null;

function drShowDate(string $date): string {
    $dt = \DateTime::createFromFormat('Y-m-d', $date);
    return $dt ? $dt->format('d/m/Y') : $date;
}
function drShowDt(string $dt): string {
    $d = \DateTime::createFromFormat('Y-m-d H:i:s', $dt);
    return $d ? $d->format('d/m/Y H:i') : $dt;
}
function drShowNum(mixed $n, int $dec = 0): string {
    return number_format((float) $n, $dec, ',', ' ');
}
?>

<?php if (isset($_GET['mail'])): ?>
    <?php if ($_GET['mail'] === '1'): ?>
        <div class="alert alert--success mb-sm"><?= __('dr_mail_sent_success') ?></div>
    <?php else: ?>
        <div class="alert alert--danger mb-sm">
            <?= __('dr_mail_sent_error') ?>
            <a href="<?= $BASE_URL ?>/admin/mail-test" class="alert__link">→ Diagnostic mail</a>
        </div>
    <?php endif; ?>
<?php endif; ?>

<?php if ($canSendMail && empty($settings['mail_recipients'])): ?>
    <div class="alert alert--warning mb-sm">
        <?= __('dr_no_recipients_warning') ?>
        <a href="<?= $BASE_URL ?>/admin/stores/<?= $storeId ?>/daily-reports/settings" class="alert__link">→ <?= __('settings') ?></a>
    </div>
<?php endif; ?>

<div class="page-header">
    <h2 class="page-header__title">
        <?= __('dr_title') ?> — <?= drShowDate($report['report_date']) ?>
        <span class="badge badge--sm <?= $badge['class'] ?>">
            <?= $badge['label'] ?>
        </span>
    </h2>
    <div class="page-header__actions">
        <a href="<?= $BASE_URL ?>/admin/stores/<?= $storeId ?>/daily-reports"
           class="btn btn--ghost">← <?= __('back') ?></a>
        <?php if (($report['status'] ?? '') === 'validated'): ?>
            <a href="<?= $BASE_URL ?>/admin/stores/<?= $storeId ?>/daily-reports/<?= $reportId ?>/pdf"
               class="btn btn--ghost" target="_blank">⬇ PDF</a>
        <?php endif; ?>
    </div>
</div>

<!-- Détails du rapport -->
<div class="card mb-sm">
    <div class="card-body">
        <div class="form-row form-row--lg dr-show-grid">

            <div>
                <table class="data-table dr-kpi-table">
                    <tbody>
                        <tr>
                            <th><?= __('dr_sales') ?></th>
                            <td class="text-right">
                                <strong><?= drShowNum($report['sales_total']) ?> <?= $currency ?></strong>
                            </td>
                        </tr>
                        <tr>
                            <th><?= __('dr_customers') ?></th>
                            <td class="text-right">
                                <strong><?= drShowNum($report['customer_count']) ?></strong>
                            </td>
                        </tr>
                        <tr>
                            <th><?= __('dr_labor') ?></th>
                            <td class="text-right">
                                <strong><?= drShowNum($report['labor_cost']) ?> <?= $currency ?></strong>
                            </td>
                        </tr>
                        <tr>
                            <th><?= __('dr_waste') ?></th>
                            <td class="text-right">
                                <strong><?= drShowNum($report['waste_total']) ?> <?= $currency ?></strong>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div>
                <table class="data-table dr-meta-table">
                    <tbody>
                        <tr>
                            <th><?= __('dr_author') ?></th>
                            <td><?= htmlspecialchars($authorName) ?></td>
                        </tr>
                        <tr>
                            <th><?= __('date') ?></th>
                            <td><?= drShowDate($report['report_date']) ?></td>
                        </tr>
                        <tr>
                            <th><?= __('created_at') ?></th>
                            <td><?= drShowDt($report['created_at']) ?></td>
                        </tr>
                        <?php if ($report['submitted_at']): ?>
                        <tr>
                            <th><?= __('dr_submitted_at') ?></th>
                            <td><?= drShowDt($report['submitted_at']) ?></td>
                        </tr>
                        <?php endif; ?>
                        <?php if ($validatorName): ?>
                        <tr>
                            <th><?= __('dr_validated_by') ?></th>
                            <td><?= htmlspecialchars($validatorName) ?></td>
                        </tr>
                        <tr>
                            <th><?= __('dr_validated_at') ?></th>
                            <td><?= drShowDt($report['validated_at'] ?? '') ?></td>
                        </tr>
                        <?php endif; ?>
                        <?php if ($report['mail_sent_at']): ?>
                        <tr>
                            <th><?= __('dr_mail_sent_at') ?></th>
                            <td><?= drShowDt($report['mail_sent_at']) ?></td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php if (!empty($report['notes'])): ?>
            <div class="mt-sm">
                <p class="form-label">(<?= __('dr_notes') ?>)</p>
                <div class="card card--shaded">
                    <?= nl2br(htmlspecialchars($report['notes'])) ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
if (!function_exists('drShowH')) {
    function drShowH(int $minutes): string {
        return intdiv($minutes, 60) . 'h' . str_pad((string) ($minutes % 60), 2, '0', STR_PAD_LEFT);
    }
}
$shiftRows = $shiftRows ?? [];
?>

<!-- Planning du jour -->
<div class="card mb-sm">
    <div class="card-header">
        <h3><?= __('dr_shifts_of_day') ?></h3>
    </div>
    <?php if (empty($shiftRows)): ?>
        <div class="card-body">
            <p class="text-muted text-italic m-0"><?= __('dr_no_shifts') ?></p>
        </div>
    <?php else:
        $showCols = $settings['show_columns'] ?? ['employee', 'type', 'schedule', 'gross_hours', 'pause', 'net_hours'];
    ?>
        <div class="table-responsive">
            <table class="data-table" data-mob-stack>
                <thead>
                    <tr>
                        <?php if (in_array('employee', $showCols, true)): ?><th><?= __('employee') ?></th><?php endif; ?>
                        <?php if (in_array('type', $showCols, true)): ?><th><?= __('shift_type') ?></th><?php endif; ?>
                        <?php if (in_array('schedule', $showCols, true)): ?><th><?= __('schedule') ?></th><?php endif; ?>
                        <?php if (in_array('gross_hours', $showCols, true)): ?><th class="td-right"><?= __('gross_h_col') ?></th><?php endif; ?>
                        <?php if (in_array('pause', $showCols, true)): ?><th class="td-right"><?= __('pause') ?></th><?php endif; ?>
                        <?php if (in_array('net_hours', $showCols, true)): ?><th class="td-right"><?= __('net_h_col') ?></th><?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($shiftRows as $row): ?>
                    <tr>
                        <?php if (in_array('employee', $showCols, true)): ?><td data-label="<?= htmlspecialchars(__('employee')) ?>"><?= htmlspecialchars($row['employee']) ?></td><?php endif; ?>
                        <?php if (in_array('type', $showCols, true)): ?><td data-label="<?= htmlspecialchars(__('shift_type')) ?>" class="td-muted"><?= htmlspecialchars($row['type']) ?></td><?php endif; ?>
                        <?php if (in_array('schedule', $showCols, true)): ?><td data-label="<?= htmlspecialchars(__('schedule')) ?>" class="td-muted td-nowrap"><?= htmlspecialchars($row['start']) ?>–<?= htmlspecialchars($row['end']) ?></td><?php endif; ?>
                        <?php if (in_array('gross_hours', $showCols, true)): ?><td data-label="<?= htmlspecialchars(__('gross_h_col')) ?>" class="td-right td-mono"><?= drShowH($row['gross_min']) ?></td><?php endif; ?>
                        <?php if (in_array('pause', $showCols, true)): ?><td data-label="<?= htmlspecialchars(__('pause')) ?>" class="td-right td-muted td-mono"><?= $row['pause_min'] > 0 ? $row['pause_min'] . ' min' : '—' ?></td><?php endif; ?>
                        <?php if (in_array('net_hours', $showCols, true)): ?><td data-label="<?= htmlspecialchars(__('net_h_col')) ?>" class="td-right td-mono"><?= drShowH($row['net_min']) ?></td><?php endif; ?>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- Actions -->
<?php if ($canEdit || $canSubmit || $canValidate || $canSendMail || $canDelete): ?>
<div class="card">
    <div class="card-body">
        <div class="btn-group">

            <?php if ($canEdit): ?>
                <a href="<?= $BASE_URL ?>/admin/stores/<?= $storeId ?>/daily-reports/<?= $reportId ?>/edit"
                   class="btn btn--primary"><?= __('edit') ?></a>
            <?php endif; ?>

            <?php if ($canSubmit): ?>
                <form method="POST"
                      action="<?= $BASE_URL ?>/admin/stores/<?= $storeId ?>/daily-reports/<?= $reportId ?>/submit"
                      onsubmit="return confirm('<?= __('dr_confirm_submit') ?>')">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn--warning"><?= __('dr_submit') ?></button>
                </form>
            <?php endif; ?>

            <?php if ($canValidate): ?>
                <form method="POST"
                      action="<?= $BASE_URL ?>/admin/stores/<?= $storeId ?>/daily-reports/<?= $reportId ?>/validate"
                      onsubmit="return confirm('<?= __('dr_confirm_validate') ?>')">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn--success"><?= __('dr_validate') ?></button>
                </form>
            <?php endif; ?>

            <?php if ($canSendMail && !empty($settings['mail_recipients'])): ?>
                <form method="POST"
                      action="<?= $BASE_URL ?>/admin/stores/<?= $storeId ?>/daily-reports/<?= $reportId ?>/send-mail"
                      onsubmit="return confirm('<?= __('dr_confirm_send_mail') ?>')">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn--ghost">✉ <?= __('dr_send_mail') ?></button>
                </form>
            <?php endif; ?>

            <?php if ($canDelete): ?>
                <form method="POST"
                      action="<?= $BASE_URL ?>/admin/stores/<?= $storeId ?>/daily-reports/<?= $reportId ?>/delete"
                      onsubmit="return confirm('<?= __('dr_confirm_delete') ?>')"
                      class="ml-auto">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn--danger"><?= __('delete') ?></button>
                </form>
            <?php endif; ?>

        </div>
    </div>
</div>
<?php endif; ?>
