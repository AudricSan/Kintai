<?php
/**
 * Formulaire store — partiel inclus par stores-form.php
 * @var string $mode   'create'|'edit'
 * @var array  $store  Données du magasin
 * @var array|null $deductionSettings  Paramètres de cotisations
 * @var array|null $importSettings  Paramètres d'import Excel / pause auto (table store_import_settings)
 */
$mode ??= 'create';
$store ??= [];
$deductionSettings ??= [];
$importSettings ??= [];

$excelDefaults = \kintai\Core\Services\ExcelShiftImport\ExcelShiftImportService::DEFAULTS;
$excelSettings = array_merge($excelDefaults, $importSettings);

$ded = $deductionSettings;
$dedEnabled     = !empty($ded['enabled']);
$dedHealth      = $ded['health_insurance_rate'] ?? 4.99;
$dedPension     = $ded['pension_rate'] ?? 9.15;
$dedEmployment  = $ded['employment_insurance_rate'] ?? 0.60;
$dedIncomeTax   = $ded['income_tax_rate'] ?? 0;
$dedResidentTax = $ded['resident_tax_monthly'] ?? 0;
?>
<div class="form-stack">
    <!-- ── Carte 1 : Informations générales ─────────────── -->
    <div class="card">
        <div class="card-header"><?= __('store_info') ?></div>
        <div class="card-body form-stack">
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label form-label--required"><?= __('code') ?></label>
                    <input type="text" name="code" class="form-control" maxlength="20"
                           value="<?= htmlspecialchars($store['code'] ?? '') ?>"
                           placeholder="ex: HQ, STORE01" required>
                    <span class="form-hint"><?= __('code') ?> court unique, sera mis en majuscules.</span>
                </div>
                <div class="form-group">
                    <label class="form-label form-label--required"><?= __('name') ?></label>
                    <input type="text" name="name" class="form-control"
                           value="<?= htmlspecialchars($store['name'] ?? '') ?>" required>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label"><?= __('type') ?></label>
                    <input type="text" name="type" class="form-control"
                           value="<?= htmlspecialchars($store['type'] ?? 'retail') ?>"
                           placeholder="ex: retail, warehouse">
                </div>
                <div class="form-group">
                    <label class="form-label form-label--required"><?= __('timezone') ?></label>
                    <input type="text" name="timezone" class="form-control"
                           value="<?= htmlspecialchars($store['timezone'] ?? 'UTC') ?>"
                           placeholder="ex: Europe/Paris, Asia/Tokyo" required>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label"><?= __('currency') ?> (code ISO)</label>
                    <input type="text" name="currency" class="form-control" maxlength="3"
                           value="<?= htmlspecialchars($store['currency'] ?? 'USD') ?>"
                           placeholder="EUR, USD, JPY">
                </div>
                <div class="form-group">
                    <label class="form-label"><?= __('locale') ?></label>
                    <input type="text" name="locale" class="form-control" maxlength="10"
                           value="<?= htmlspecialchars($store['locale'] ?? 'en') ?>"
                           placeholder="fr, en, ja">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label"><?= __('phone') ?></label>
                    <input type="tel" name="phone" class="form-control"
                           value="<?= htmlspecialchars($store['phone'] ?? '') ?>"
                           placeholder="+33 1 00 00 00 00">
                </div>
                <div class="form-group">
                    <label class="form-label"><?= __('email') ?></label>
                    <input type="email" name="email" class="form-control"
                           value="<?= htmlspecialchars($store['email'] ?? '') ?>"
                           placeholder="contact@magasin.com">
                </div>
            </div>
            <div class="form-group">
                <label class="form-label"><?= __('address') ?></label>
                <input type="text" name="address_street" class="form-control"
                       value="<?= htmlspecialchars($store['address_street'] ?? '') ?>"
                       placeholder="<?= __('street_placeholder') ?>">
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label"><?= __('postal_code') ?></label>
                    <input type="text" name="address_postal" class="form-control" maxlength="20"
                           value="<?= htmlspecialchars($store['address_postal'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label"><?= __('city') ?></label>
                    <input type="text" name="address_city" class="form-control"
                           value="<?= htmlspecialchars($store['address_city'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label"><?= __('country') ?></label>
                    <input type="text" name="address_country" class="form-control" maxlength="100"
                           value="<?= htmlspecialchars($store['address_country'] ?? '') ?>"
                           placeholder="France">
                </div>
            </div>
            <?php if ($mode === 'edit'): ?>
            <div class="form-group">
                <label class="form-label"><?= __('status') ?></label>
                <select name="is_active" class="form-control">
                    <option value="1" <?= !empty($store['is_active']) ? 'selected' : '' ?>><?= __('active') ?></option>
                    <option value="0" <?= empty($store['is_active']) ? 'selected' : '' ?>><?= __('inactive') ?></option>
                </select>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ── Carte 2 : Cotisations sociales ──────────────── -->
    <div class="card card--mt">
        <div class="card-header"><?= __('deduction_settings') ?></div>
        <div class="card-body form-stack">
            <div class="form-group form-group--inline">
                <label class="form-check">
                    <input type="checkbox" name="ded_enabled" value="1" <?= $dedEnabled ? 'checked' : '' ?>>
                    <span><?= __('deductions_enabled') ?></span>
                </label>
            </div>
            <div id="ded-fields" <?= $dedEnabled ? '' : 'hidden' ?>>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label"><?= __('ded_health_insurance') ?> (%)</label>
                        <input type="number" name="ded_health_rate" class="form-control" step="0.01" min="0" max="100"
                               value="<?= htmlspecialchars((string) $dedHealth) ?>">
                        <span class="form-hint"><?= __('ded_rate_hint') ?></span>
                    </div>
                    <div class="form-group">
                        <label class="form-label"><?= __('ded_pension') ?> (%)</label>
                        <input type="number" name="ded_pension_rate" class="form-control" step="0.01" min="0" max="100"
                               value="<?= htmlspecialchars((string) $dedPension) ?>">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label"><?= __('ded_employment_insurance') ?> (%)</label>
                        <input type="number" name="ded_employment_rate" class="form-control" step="0.01" min="0" max="100"
                               value="<?= htmlspecialchars((string) $dedEmployment) ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label"><?= __('ded_income_tax') ?> (%)</label>
                        <input type="number" name="ded_income_tax_rate" class="form-control" step="0.01" min="0" max="100"
                               value="<?= htmlspecialchars((string) $dedIncomeTax) ?>">
                        <span class="form-hint"><?= __('ded_income_tax_hint') ?></span>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label"><?= __('ded_resident_tax') ?> (<?= __('monthly_fixed') ?>)</label>
                        <input type="number" name="ded_resident_tax" class="form-control" step="1" min="0"
                               value="<?= htmlspecialchars((string) $dedResidentTax) ?>">
                        <span class="form-hint"><?= __('ded_resident_tax_hint') ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ── Carte 3 : Import Excel ──────────────────────── -->
    <div class="card card--mt">
        <div class="card-header">
            <span><?= __('excel_import_settings') ?></span>
            <a href="<?= $BASE_URL ?>/assets/doc/template.xlsm" download
               class="btn btn--ghost btn--sm">↓ <?= __('excel_download_template') ?></a>
        </div>
        <div class="card-body form-stack">
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label"><?= __('excel_col_start') ?> (col_start)</label>
                    <input type="number" name="excel_col_start" class="form-control" min="1"
                           value="<?= (int) $excelSettings['col_start'] ?>">
                    <span class="form-hint"><?= __('excel_col_start_hint') ?></span>
                </div>
                <div class="form-group">
                    <label class="form-label"><?= __('excel_col_end') ?> (col_end)</label>
                    <input type="number" name="excel_col_end" class="form-control" min="1"
                           value="<?= (int) $excelSettings['col_end'] ?>">
                    <span class="form-hint"><?= __('excel_col_end_hint') ?></span>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label"><?= __('excel_base_hour') ?> (base_hour)</label>
                    <input type="number" name="excel_base_hour" class="form-control" min="0" max="23"
                           value="<?= (int) $excelSettings['base_hour'] ?>">
                    <span class="form-hint"><?= __('excel_base_hour_hint') ?></span>
                </div>
                <div class="form-group">
                    <label class="form-label"><?= __('excel_minutes_per_col') ?> (minutes_per_col)</label>
                    <input type="number" name="excel_minutes_per_col" class="form-control" min="1"
                           value="<?= (int) $excelSettings['minutes_per_col'] ?>">
                    <span class="form-hint"><?= __('excel_minutes_per_col_hint') ?></span>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label"><?= __('excel_block_size') ?> (block_size)</label>
                    <input type="number" name="excel_block_size" class="form-control" min="2"
                           value="<?= (int) $excelSettings['block_size'] ?>">
                    <span class="form-hint"><?= __('excel_block_size_hint') ?></span>
                </div>
                <div class="form-group">
                    <label class="form-label"><?= __('excel_shift_rows') ?> (shift_rows)</label>
                    <input type="number" name="excel_shift_rows" class="form-control" min="1"
                           value="<?= (int) $excelSettings['shift_rows'] ?>">
                    <span class="form-hint"><?= __('excel_shift_rows_hint') ?></span>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label"><?= __('excel_sheet_filter_pattern') ?> (sheet_filter_pattern)</label>
                    <input type="text" name="excel_sheet_filter_pattern" class="form-control"
                           value="<?= htmlspecialchars($excelSettings['sheet_filter_pattern']) ?>">
                    <span class="form-hint"><?= __('excel_sheet_filter_pattern_hint') ?></span>
                </div>
            </div>
        </div>
    </div>

    <!-- ── Carte 4 : Paramètres de planning ────────────── -->
    <div class="card card--mt">
        <div class="card-header"><?= __('shift_settings') ?></div>
        <div class="card-body form-stack">
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label"><?= __('week_start_day') ?></label>
                    <select name="week_start_day" class="form-control">
                        <?php
                        $wsdCurrent = (int) ($store['week_start_day'] ?? 1);
                        $wsdOptions = [1=>'monday',2=>'tuesday',3=>'wednesday',4=>'thursday',5=>'friday',6=>'saturday',7=>'sunday'];
                        foreach ($wsdOptions as $wsdVal => $wsdKey): ?>
                            <option value="<?= $wsdVal ?>"<?= $wsdCurrent === $wsdVal ? ' selected' : '' ?>><?= __($wsdKey) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <span class="form-hint"><?= __('week_start_day_hint') ?></span>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label"><?= __('min_shift_duration') ?></label>
                    <input type="number" name="min_shift_minutes" class="form-control" min="0"
                           value="<?= (int) ($store['min_shift_minutes'] ?? 120) ?>">
                    <span class="form-hint"><?= __('min_shift_minutes_hint') ?></span>
                </div>
                <div class="form-group">
                    <label class="form-label"><?= __('max_shift_duration') ?></label>
                    <input type="number" name="max_shift_minutes" class="form-control" min="0"
                           value="<?= (int) ($store['max_shift_minutes'] ?? 480) ?>">
                    <span class="form-hint"><?= __('max_shift_minutes_hint') ?></span>
                </div>
                <div class="form-group">
                    <label class="form-label"><?= __('min_staff_per_day') ?></label>
                    <input type="number" name="min_staff_per_day" class="form-control" min="0"
                           value="<?= (int) ($store['min_staff_per_day'] ?? 0) ?>">
                    <span class="form-hint"><?= __('min_staff_per_day_hint') ?></span>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group form-group--full">
                    <label class="form-label"><?= __('low_staff_hours') ?></label>
                    <div class="store-hour-row">
                        <div>
                            <span class="form-hint form-hint--label"><?= __('low_staff_hour_start') ?></span>
                            <input type="number" name="low_staff_hour_start" class="form-control store-hour-input" min="-1" max="23"
                                   value="<?= (int) ($store['low_staff_hour_start'] ?? -1) ?>">
                        </div>
                        <span class="store-hour-sep">→</span>
                        <div>
                            <span class="form-hint form-hint--label"><?= __('low_staff_hour_end') ?></span>
                            <input type="number" name="low_staff_hour_end" class="form-control store-hour-input" min="-1" max="23"
                                   value="<?= (int) ($store['low_staff_hour_end'] ?? -1) ?>">
                        </div>
                    </div>
                    <span class="form-hint"><?= __('low_staff_hours_hint') ?></span>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label"><?= __('auto_pause_threshold') ?></label>
                    <input type="number" name="auto_pause_after_minutes" class="form-control" min="0"
                           value="<?= (int) ($excelSettings['auto_pause_after_minutes'] ?? 0) ?>">
                    <span class="form-hint"><?= __('auto_pause_threshold_hint') ?></span>
                </div>
                <div class="form-group">
                    <label class="form-label"><?= __('auto_pause_duration') ?></label>
                    <input type="number" name="auto_pause_minutes" class="form-control" min="1"
                           value="<?= (int) ($excelSettings['auto_pause_minutes'] ?? 30) ?>">
                    <span class="form-hint"><?= __('auto_pause_duration_hint') ?></span>
                </div>
            </div>
        </div>
    </div>

    <!-- ── Carte 5 : Fonctionnalités ───────────────────── -->
    <div class="card card--mt">
        <div class="card-header"><?= __('store_features') ?></div>
        <div class="card-body form-stack">
            <p class="form-hint"><?= __('store_features_hint') ?></p>
            <?php
            $allFeatureSlugs = [
                'shifts'        => ['label' => __('feature_shifts'),        'desc' => __('feature_shifts_desc'),        'icon' => '📅'],
                'timeclock'     => ['label' => __('feature_timeclock'),      'desc' => __('feature_timeclock_desc'),      'icon' => '🕐'],
                'timeoff'       => ['label' => __('feature_timeoff'),        'desc' => __('feature_timeoff_desc'),        'icon' => '🏖'],
                'swaps'         => ['label' => __('feature_swaps'),          'desc' => __('feature_swaps_desc'),          'icon' => '🔄'],
                'open_shifts'   => ['label' => __('feature_open_shifts'),    'desc' => __('feature_open_shifts_desc'),    'icon' => '📋'],
                'messages'      => ['label' => __('feature_messages'),       'desc' => __('feature_messages_desc'),       'icon' => '💬'],
                'daily_reports' => ['label' => __('feature_daily_reports'),  'desc' => __('feature_daily_reports_desc'),  'icon' => '📊'],
                'photos'        => ['label' => __('feature_photos'),        'desc' => __('feature_photos_desc'),        'icon' => '📷'],
            ];
            $enabledFeatures ??= null;
            $isEnabled = fn(string $slug): bool => $enabledFeatures === null || in_array($slug, $enabledFeatures, true);
            // Un slug lié à un bundle désactivé au niveau instance (/admin/bundles) n'est pas proposé ici.
            $availableFeatureSlugs ??= [];
            $isAvailable = fn(string $slug): bool => $availableFeatureSlugs[$slug] ?? true;
            ?>
            <div class="feature-cards">
                <?php foreach ($allFeatureSlugs as $slug => $info): ?>
                    <?php if (!$isAvailable($slug)) continue; ?>
                    <?php $on = $isEnabled($slug); ?>
                    <label class="feature-card">
                        <input type="checkbox" name="feature_<?= $slug ?>" value="1"
                               <?= $on ? 'checked' : '' ?> class="feature-card__input">
                        <div class="feature-card__icon"><?= $info['icon'] ?></div>
                        <div class="feature-card__body">
                            <span class="feature-card__title"><?= htmlspecialchars($info['label']) ?></span>
                            <span class="feature-card__desc"><?= htmlspecialchars($info['desc']) ?></span>
                        </div>
                        <div class="feature-card__toggle">
                            <span class="toggle-switch">
                                <span class="toggle-switch__track"></span>
                                <span class="toggle-switch__thumb"></span>
                            </span>
                            <span class="toggle-switch__label">
                                <?= $on ? __('active') : __('inactive') ?>
                            </span>
                        </div>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>
