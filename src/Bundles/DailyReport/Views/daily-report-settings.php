<?php
/**
 * @var array  $store
 * @var array  $settings
 * @var array  $errors
 * @var string $BASE_URL
 */
$storeId = (int) $store['id'];
$action  = $BASE_URL . '/admin/stores/' . $storeId . '/daily-reports/settings';

$recipients        = implode("\n", $settings['mail_recipients'] ?? []);
$autoValidateTime  = $settings['auto_validate_time'] ?? '';
$reminderTime      = $settings['reminder_time']      ?? '15:00';
$noReportTime      = $settings['no_report_time']     ?? '18:00';
?>

<div class="page-header">
    <h2 class="page-header__title">
        <?= __('dr_settings') ?> — <?= htmlspecialchars($store['name']) ?>
    </h2>
    <div class="page-header__actions">
        <a href="<?= $BASE_URL ?>/admin/stores/<?= $storeId ?>/daily-reports"
           class="btn btn--ghost btn--sm">← <?= __('back') ?></a>
    </div>
</div>

<?= \kintai\UI\Components\Flash::fromQuery('success', [])->render() ?>

<?php if (!empty($errors)): ?>
    <div class="alert alert--danger mb-sm">
        <?php foreach ($errors as $msg): ?>
            <div><?= htmlspecialchars($msg) ?></div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<form method="POST" action="<?= htmlspecialchars($action) ?>">
<?= csrf_field() ?>

    <!-- ── Activation ── -->
    <div class="card card--mb">
        <div class="card-header"><?= __('dr_setting_enabled') ?></div>
        <div class="card-body form-stack">
            <label class="check-label">
                <input type="checkbox" name="enabled" value="1"
                       <?= !empty($settings['enabled']) ? 'checked' : '' ?>>
                <span><?= __('dr_setting_enabled_hint') ?></span>
            </label>
            <div class="form-group">
                <label class="form-label"><?= __('dr_setting_cumulative') ?></label>
                <select name="cumulative_mode" class="form-control form-control--sm">
                    <option value="per_day" <?= $settings['cumulative_mode'] === 'per_day' ? 'selected' : '' ?>><?= __('dr_mode_per_day') ?></option>
                    <option value="cumulative_system" <?= $settings['cumulative_mode'] === 'cumulative_system' ? 'selected' : '' ?>><?= __('dr_mode_cumulative_system') ?></option>
                    <option value="cumulative_input" <?= $settings['cumulative_mode'] === 'cumulative_input' ? 'selected' : '' ?>><?= __('dr_mode_cumulative_input') ?></option>
                </select>
            </div>
        </div>
    </div>

    <!-- ── Permissions ── -->
    <div class="card card--mb">
        <div class="card-header"><?= __('dr_setting_permissions') ?></div>
        <div class="card-body">
            <p class="form-hint">
                <?= __('dr_setting_permissions_hint') ?>
                <a href="<?= route_url('admin.roles') ?>"><?= __('roles') ?></a>
            </p>
        </div>
    </div>

    <!-- ── Notifications e-mail ── -->
    <div class="card card--mb">
        <div class="card-header"><?= __('dr_setting_notifications') ?></div>
        <div class="card-body form-stack">

            <div class="form-group">
                <label class="form-label"><?= __('dr_setting_recipients') ?></label>
                <textarea name="mail_recipients" class="form-control" rows="4"
                          placeholder="manager@example.com&#10;direction@example.com"
                ><?= htmlspecialchars($recipients) ?></textarea>
                <span class="form-hint"><?= __('dr_setting_recipients_hint') ?></span>
            </div>

            <div class="form-group">
                <label class="form-label"><?= __('dr_setting_auto_send') ?></label>
                <label class="check-label">
                    <input type="checkbox" name="auto_send_on_validate" value="1"
                           <?= !empty($settings['auto_send_on_validate']) ? 'checked' : '' ?>>
                    <span><?= __('dr_setting_auto_send_hint') ?></span>
                </label>
            </div>

        </div>
    </div>

    <!-- ── Colonnes du planning ── -->
    <div class="card card--mb">
        <div class="card-header"><?= __('dr_setting_columns') ?></div>
        <div class="card-body form-stack">
            <p class="form-hint mb-sm"><?= __('dr_setting_columns_hint') ?></p>
            <div class="check-group">
                <?php
                $allCols = [
                    'employee'    => __('employee'),
                    'type'        => __('shift_type'),
                    'schedule'    => __('schedule'),
                    'gross_hours' => __('gross_h_col'),
                    'pause'       => __('pause'),
                    'net_hours'   => __('net_h_col'),
                ];
                $showCols = $settings['show_columns'] ?? array_keys($allCols);
                foreach ($allCols as $colKey => $colLabel):
                ?>
                    <label class="check-label">
                        <input type="checkbox"
                               name="show_columns[]"
                               value="<?= $colKey ?>"
                               class="dr-col-toggle"
                               data-col="<?= $colKey ?>"
                               <?= in_array($colKey, $showCols, true) ? 'checked' : '' ?>>
                        <span><?= htmlspecialchars($colLabel) ?></span>
                    </label>
                <?php endforeach; ?>
            </div>

            <!-- Aperçu -->
            <div class="mt-sm">
                <label class="form-label"><?= __('dr_setting_columns_preview') ?></label>
                <div class="table-responsive">
                    <table class="data-table" id="drColumnPreview">
                        <thead>
                            <tr>
                                <?php if (in_array('employee', $showCols, true)): ?><th data-col="employee"><?= __('employee') ?></th><?php endif; ?>
                                <?php if (in_array('type', $showCols, true)): ?><th data-col="type"><?= __('shift_type') ?></th><?php endif; ?>
                                <?php if (in_array('schedule', $showCols, true)): ?><th data-col="schedule"><?= __('schedule') ?></th><?php endif; ?>
                                <?php if (in_array('gross_hours', $showCols, true)): ?><th data-col="gross_hours" class="td-right"><?= __('gross_h_col') ?></th><?php endif; ?>
                                <?php if (in_array('pause', $showCols, true)): ?><th data-col="pause" class="td-right"><?= __('pause') ?></th><?php endif; ?>
                                <?php if (in_array('net_hours', $showCols, true)): ?><th data-col="net_hours" class="td-right"><?= __('net_h_col') ?></th><?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <?php if (in_array('employee', $showCols, true)): ?><td data-col="employee"><?= __('dr_col_preview_employee') ?></td><?php endif; ?>
                                <?php if (in_array('type', $showCols, true)): ?><td data-col="type" class="td-muted"><?= __('dr_col_preview_type') ?></td><?php endif; ?>
                                <?php if (in_array('schedule', $showCols, true)): ?><td data-col="schedule" class="td-muted td-nowrap"><?= __('dr_col_preview_schedule') ?></td><?php endif; ?>
                                <?php if (in_array('gross_hours', $showCols, true)): ?><td data-col="gross_hours" class="td-right td-mono"><?= __('dr_col_preview_gross') ?></td><?php endif; ?>
                                <?php if (in_array('pause', $showCols, true)): ?><td data-col="pause" class="td-right td-muted td-mono"><?= __('dr_col_preview_pause') ?></td><?php endif; ?>
                                <?php if (in_array('net_hours', $showCols, true)): ?><td data-col="net_hours" class="td-right td-mono"><?= __('dr_col_preview_net') ?></td><?php endif; ?>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="form-actions">
        <button type="submit" class="btn btn--primary"><?= __('save') ?></button>
        <a href="<?= $BASE_URL ?>/admin/stores/<?= $storeId ?>/daily-reports"
           class="btn btn--ghost"><?= __('cancel') ?></a>
    </div>

</form>

<script>
(function() {
    var toggles = document.querySelectorAll('.dr-col-toggle');
    toggles.forEach(function(cb) {
        cb.addEventListener('change', function() {
            var col = this.dataset.col;
            var previewCells = document.querySelectorAll('#drColumnPreview [data-col="' + col + '"]');
            previewCells.forEach(function(el) {
                if (el.tagName === 'TH' || el.tagName === 'TD') {
                    el.style.display = cb.checked ? '' : 'none';
                }
            });
        });
    });
})();
</script>
