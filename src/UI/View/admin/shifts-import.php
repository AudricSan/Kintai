<?php
/** @var array       $all_stores */
/** @var string|null $error */
?>

<?php if ($error): ?>
    <div class="alert alert--danger">
        <?= match($error) {
            'upload' => __('upload_error'),
            'parse'  => __('parse_error'),
            'empty'  => __('empty_file_error'),
            default  => __('operation_success'),
        } ?>
    </div>
<?php endif; ?>

<div class="page-header">
    <h2 class="page-header__title"><?= __('import_excel_title') ?></h2>
    <a href="<?= $BASE_URL ?>/admin/shifts" class="btn btn--ghost">← <?= __('back') ?></a>
</div>

<div class="card card--narrow">
    <div class="card-header"><?= __('excel_file_label') ?></div>
    <div class="card-body">
        <form method="POST" action="<?= $BASE_URL ?>/admin/shifts/import" enctype="multipart/form-data">
            <div class="form-group">
                <label class="form-label"><?= __('destination_store') ?> <span class="required">*</span></label>
                <select name="store_id" class="form-control" required>
                    <option value="">— <?= __('select') ?> —</option>
                    <?php foreach ($all_stores as $s): ?>
                        <option value="<?= (int) $s['id'] ?>"><?= htmlspecialchars($s['name'] ?? '') ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group import-form-group--mt">
                <label class="form-label"><?= __('excel_file_label') ?> <span class="required">*</span></label>
                <input type="file" name="excel_file" class="form-control" accept=".xlsx,.xlsm" required>
                <div class="form-hint">
                    <?= __('excel_format_hint') ?>
                </div>
            </div>

            <div class="import-form-footer">
                <button type="submit" class="btn btn--primary"><?= __('analyze_file') ?></button>
            </div>
        </form>
    </div>
</div>
