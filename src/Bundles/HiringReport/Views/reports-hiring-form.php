<?php
use kintai\UI\Components\Button;
use kintai\UI\Components\Card;
use kintai\UI\Components\Flash;

/**
 * @var array  $store
 * @var array  $users
 * @var string $mode   'create'|'edit'
 * @var array  $report
 */
$storeId = (int) $store['id'];
$report  ??= [];
$reportId = (int) ($report['id'] ?? 0);
$base = $BASE_URL . '/admin/stores/' . $storeId . '/reports/hiring';
$action = $mode === 'edit'
    ? $base . '/' . $reportId . '/edit'
    : $base . '/create';
$title = $mode === 'edit' ? __('edit_hiring_report') : __('new_hiring_report');

echo Flash::fromQuery('error', ['already_exists' => __('report_already_exists')])->render();
?>
<div class="page-header">
    <h2 class="page-header__title"><?= htmlspecialchars($title) ?></h2>
    <div class="page-header__actions">
        <a href="<?= htmlspecialchars($base) ?>" class="btn btn--ghost">← <?= __('back') ?></a>
    </div>
</div>

<?php
ob_start();
echo '<form method="POST" action="' . htmlspecialchars($action) . '">';
echo csrf_field();
?>
    <div class="form-row form-row--2col">
        <div class="form-group">
            <label class="form-label" for="hr-user_id"><?= __('user') ?></label>
            <select id="hr-user_id" name="user_id" class="form-control">
                <option value="">— <?= __('select') ?> —</option>
                <?php foreach ($users as $u): ?>
                    <option value="<?= (int) $u['id'] ?>" <?= (int) ($report['user_id'] ?? 0) === (int) $u['id'] ? 'selected' : '' ?>><?= htmlspecialchars($u['display_name'] ?? ($u['last_name'] ?? '') . ' ' . ($u['first_name'] ?? '')) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label class="form-label" for="hr-employee_number"><?= __('employee_number') ?></label>
            <input type="text" id="hr-employee_number" name="employee_number" class="form-control" value="<?= htmlspecialchars($report['employee_number'] ?? '') ?>">
        </div>
    </div>
    <div class="form-row form-row--2col">
        <div class="form-group">
            <label class="form-label" for="hr-employee_name"><?= __('employee_name') ?></label>
            <input type="text" id="hr-employee_name" name="employee_name" class="form-control" value="<?= htmlspecialchars($report['employee_name'] ?? '') ?>">
        </div>
        <div class="form-group">
            <label class="form-label"><?= __('furigana_last_name') ?></label>
            <input type="text" name="furigana_last_name" class="form-control" value="<?= htmlspecialchars($report['furigana_last_name'] ?? '') ?>" placeholder="カタカナ">
        </div>
        <div class="form-group">
            <label class="form-label"><?= __('furigana_first_name') ?></label>
            <input type="text" name="furigana_first_name" class="form-control" value="<?= htmlspecialchars($report['furigana_first_name'] ?? '') ?>" placeholder="カタカナ">
        </div>
    </div>
    <div class="form-row form-row--3col">
        <div class="form-group">
            <label class="form-label" for="hr-gender"><?= __('gender') ?></label>
            <select id="hr-gender" name="gender" class="form-control">
                <option value="">— <?= __('select') ?> —</option>
                <option value="male" <?= ($report['gender'] ?? '') === 'male' ? 'selected' : '' ?>><?= __('male') ?></option>
                <option value="female" <?= ($report['gender'] ?? '') === 'female' ? 'selected' : '' ?>><?= __('female') ?></option>
            </select>
        </div>
        <div class="form-group">
            <label class="form-label" for="hr-tax_classification"><?= __('tax_classification') ?></label>
            <select id="hr-tax_classification" name="tax_classification" class="form-control">
                <option value="">— <?= __('select') ?> —</option>
                <option value="kou" <?= ($report['tax_classification'] ?? '') === 'kou' ? 'selected' : '' ?>><?= __('tax_kou') ?></option>
                <option value="otsu" <?= ($report['tax_classification'] ?? '') === 'otsu' ? 'selected' : '' ?>><?= __('tax_otsu') ?></option>
            </select>
        </div>
        <div class="form-group">
            <label class="form-label" for="hr-education"><?= __('education') ?></label>
            <input type="text" id="hr-education" name="education" class="form-control" value="<?= htmlspecialchars($report['education'] ?? '') ?>">
        </div>
    </div>
    <div class="form-row form-row--2col">
        <div class="form-group">
            <label class="form-label" for="hr-birth_date"><?= __('birth_date') ?></label>
            <input type="date" id="hr-birth_date" name="birth_date" class="form-control" value="<?= htmlspecialchars($report['birth_date'] ?? '') ?>">
        </div>
        <div class="form-group">
            <label class="form-label" for="hr-hire_date"><?= __('hire_date') ?></label>
            <input type="date" id="hr-hire_date" name="hire_date" class="form-control" value="<?= htmlspecialchars($report['hire_date'] ?? '') ?>">
        </div>
    </div>
    <div class="form-row form-row--2col">
        <div class="form-group">
            <label class="form-label" for="hr-postal_code"><?= __('postal_code') ?></label>
            <input type="text" id="hr-postal_code" name="postal_code" class="form-control" value="<?= htmlspecialchars($report['postal_code'] ?? '') ?>">
        </div>
        <div class="form-group">
            <label class="form-label" for="hr-address"><?= __('address') ?></label>
            <textarea id="hr-address" name="address" class="form-control" rows="3"><?= htmlspecialchars($report['address'] ?? '') ?></textarea>
        </div>
    </div>
    <div class="form-row form-row--2col">
        <div class="form-group">
            <label class="form-label" for="hr-phone"><?= __('phone') ?></label>
            <input type="text" id="hr-phone" name="phone" class="form-control" value="<?= htmlspecialchars($report['phone'] ?? '') ?>">
        </div>
        <div class="form-group">
            <label class="form-label" for="hr-mobile_phone"><?= __('mobile_phone') ?></label>
            <input type="text" id="hr-mobile_phone" name="mobile_phone" class="form-control" value="<?= htmlspecialchars($report['mobile_phone'] ?? '') ?>">
        </div>
    </div>
    <div class="form-row form-row--2col">
        <div class="form-group">
            <label class="form-label" for="hr-email"><?= __('email') ?></label>
            <input type="email" id="hr-email" name="email" class="form-control" value="<?= htmlspecialchars($report['email'] ?? '') ?>">
        </div>
        <div class="form-group">
            <label class="form-label" for="hr-store_name"><?= __('store_name') ?></label>
            <input type="text" id="hr-store_name" name="store_name" class="form-control" value="<?= htmlspecialchars($report['store_name'] ?? $store['name'] ?? '') ?>">
        </div>
    </div>
    <div class="form-row form-row--2col">
        <div class="form-group">
            <label class="form-label" for="hr-guarantor_name"><?= __('guarantor_name') ?></label>
            <input type="text" id="hr-guarantor_name" name="guarantor_name" class="form-control" value="<?= htmlspecialchars($report['guarantor_name'] ?? '') ?>">
        </div>
        <div class="form-group">
            <label class="form-label" for="hr-guarantor_phone"><?= __('guarantor_phone') ?></label>
            <input type="text" id="hr-guarantor_phone" name="guarantor_phone" class="form-control" value="<?= htmlspecialchars($report['guarantor_phone'] ?? '') ?>">
        </div>
    </div>
    <div class="form-row form-row--2col">
        <div class="form-group">
            <label class="form-label" for="hr-hired_by"><?= __('hired_by') ?></label>
            <input type="text" id="hr-hired_by" name="hired_by" class="form-control" value="<?= htmlspecialchars($report['hired_by'] ?? '') ?>">
        </div>
    </div>
    <div class="form-group">
        <label class="form-label" for="hr-notes"><?= __('notes') ?></label>
        <textarea id="hr-notes" name="notes" class="form-control" rows="4"><?= htmlspecialchars($report['notes'] ?? '') ?></textarea>
    </div>
    <div class="form-actions">
        <?= Button::make($mode === 'edit' ? __('save') : __('new_hiring_report'))->primary()->submit()->render() ?>
        <a href="<?= htmlspecialchars($base) ?>" class="btn btn--ghost"><?= __('cancel') ?></a>
    </div>
</form>
<?php
echo Card::make()->body(ob_get_clean())->render();
?>
