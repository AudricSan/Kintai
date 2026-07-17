<?php
/** @var string $mode       'create'|'edit' */
/** @var array  $shift_type Données du type de shift */
/** @var array  $all_stores Liste des magasins */
$mode ??= 'create';
?>
<div class="form-stack">
    <!-- ── Store ────────────────────────────────── -->
    <div class="form-group">
        <label class="form-label form-label--required"><?= __('store') ?></label>
        <select name="store_id" class="form-control" required>
            <option value="">— <?= __('select') ?> —</option>
            <?php foreach ($all_stores as $s): ?>
                <option value="<?= (int) $s['id'] ?>"
                    <?= (int) ($shift_type['store_id'] ?? 0) === (int) $s['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($s['name'] ?? '') ?> (<?= htmlspecialchars($s['code'] ?? '') ?>)
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <!-- ── Code & nom ───────────────────────────── -->
    <div class="form-row">
        <div class="form-group">
            <label class="form-label form-label--required"><?= __('code') ?></label>
            <input type="text" name="code" class="form-control" maxlength="20"
                   value="<?= htmlspecialchars($shift_type['code'] ?? '') ?>"
                   placeholder="ex: MATIN, APREM, NUIT" required>
        </div>
        <div class="form-group">
            <label class="form-label form-label--required"><?= __('name') ?></label>
            <input type="text" name="name" class="form-control"
                   value="<?= htmlspecialchars($shift_type['name'] ?? '') ?>"
                   placeholder="<?= __('shift_type_placeholder') ?>" required>
        </div>
    </div>

    <!-- ── Horaires par défaut ───────────────────── -->
    <div class="form-row">
        <div class="form-group">
            <label class="form-label form-label--required"><?= __('start') ?></label>
            <input type="time" name="start_time" class="form-control"
                   value="<?= htmlspecialchars($shift_type['start_time'] ?? '08:00') ?>" required>
        </div>
        <div class="form-group">
            <label class="form-label form-label--required"><?= __('end') ?></label>
            <input type="time" name="end_time" class="form-control"
                   value="<?= htmlspecialchars($shift_type['end_time'] ?? '16:00') ?>" required>
        </div>
    </div>

    <!-- ── Taux horaire ─────────────────────────── -->
    <div class="form-group">
        <label class="form-label"><?= __('hourly_rate') ?> (/h)</label>
        <input type="number" name="hourly_rate" class="form-control"
               min="0" step="0.01"
               value="<?= number_format((float) ($shift_type['hourly_rate'] ?? 0), 2, '.', '') ?>"
               placeholder="0.00">
        <span class="form-hint"><?= __('hourly_rate_hint') ?></span>
    </div>

    <!-- ── Couleur & statut ─────────────────────── -->
    <div class="form-row">
        <div class="form-group">
            <label class="form-label"><?= __('color') ?></label>
            <div class="input-group">
                <input type="color" name="color" class="input-color"
                       value="<?= htmlspecialchars($shift_type['color'] ?? '#6366f1') ?>">
                <span class="text-sm-muted"><?= __('color_hint') ?></span>
            </div>
        </div>
        <?php if ($mode === 'edit'): ?>
        <div class="form-group">
            <label class="form-label"><?= __('status') ?></label>
            <select name="is_active" class="form-control">
                <option value="1" <?= !empty($shift_type['is_active']) ? 'selected' : '' ?>><?= __('active') ?></option>
                <option value="0" <?= empty($shift_type['is_active']) ? 'selected' : '' ?>><?= __('inactive') ?></option>
            </select>
        </div>
        <?php endif; ?>
    </div>
</div>
