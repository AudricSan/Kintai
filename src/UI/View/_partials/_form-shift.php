<?php
/** @var array  $shift        Données du shift (valeurs pré-remplies ou vides) */
/** @var array  $all_users    Liste des utilisateurs */
/** @var array  $all_stores   Liste des magasins */
/** @var array  $all_types    Liste des types de shift */
/** @var array|null $store_features  Fonctionnalités activées ou null */
$feat = $feat ?? fn(string $f): bool =>
    !isset($store_features) || $store_features === null
    || in_array($f, (array) $store_features, true);
?>
<div class="form-stack">
    <!-- ── Store & Utilisateur ─────────────────────────────── -->
    <div class="form-row">
        <div class="form-group">
            <label class="form-label form-label--required"><?= __('store') ?></label>
            <?php if (count($all_stores) === 1): ?>
                <?php $onlyStore = $all_stores[0]; ?>
                <input type="hidden" name="store_id" value="<?= (int) $onlyStore['id'] ?>">
                <div class="form-control form-control--readonly">
                    <?= htmlspecialchars($onlyStore['name'] ?? '') ?> (<?= htmlspecialchars($onlyStore['code'] ?? '') ?>)
                </div>
            <?php else: ?>
                <select name="store_id" class="form-control" required>
                    <option value="">— <?= __('select') ?> —</option>
                    <?php foreach ($all_stores as $s): ?>
                        <option value="<?= (int) $s['id'] ?>"
                            <?= (int) ($shift['store_id'] ?? 0) === (int) $s['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($s['name'] ?? '') ?> (<?= htmlspecialchars($s['code'] ?? '') ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            <?php endif; ?>
        </div>
        <div class="form-group">
            <label class="form-label form-label--required"><?= __('user') ?></label>
            <select name="user_id" class="form-control" required>
                <option value="">— <?= __('select') ?> —</option>
                <?php foreach ($all_users as $u): ?>
                    <option value="<?= (int) $u['id'] ?>"
                        <?= (int) ($shift['user_id'] ?? 0) === (int) $u['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($u['display_name'] ?? $u['email'] ?? '') ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <!-- ── Date & Type de shift ─────────────────────────────── -->
    <div class="form-row">
        <div class="form-group">
            <label class="form-label form-label--required"><?= __('date') ?></label>
            <input type="date" name="shift_date" class="form-control"
                   value="<?= htmlspecialchars($shift['shift_date'] ?? '') ?>" required>
        </div>
        <div class="form-group">
            <label class="form-label"><?= __('type') ?></label>
            <select name="shift_type_id" class="form-control">
                <option value="">— <?= __('none') ?> —</option>
                <?php foreach ($all_types as $t): ?>
                    <option value="<?= (int) $t['id'] ?>"
                        <?= (int) ($shift['shift_type_id'] ?? 0) === (int) $t['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($t['name'] ?? '') ?> (<?= htmlspecialchars($t['code'] ?? '') ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <!-- ── Horaires (début / fin) ────────────────────────────── -->
    <div class="form-row">
        <div class="form-group">
            <label class="form-label form-label--required"><?= __('start') ?></label>
            <input type="time" name="start_time" class="form-control"
                   value="<?= htmlspecialchars($shift['start_time'] ?? '08:00') ?>" required>
        </div>
        <div class="form-group">
            <label class="form-label form-label--required"><?= __('end') ?></label>
            <input type="time" name="end_time" class="form-control"
                   value="<?= htmlspecialchars($shift['end_time'] ?? '16:00') ?>" required>
        </div>
    </div>

    <!-- ── Pause ─────────────────────────────────────────────── -->
    <div class="form-row">
        <div class="form-group">
            <label class="form-label"><?= __('pause') ?> (minutes)</label>
            <input type="number" name="pause_minutes" class="form-control" min="0" max="120"
                   value="<?= (int) ($shift['pause_minutes'] ?? 0) ?>">
        </div>
    </div>

    <!-- ── Ajustement manuel (taux/heures actives) ──────────────
         Vide = calcul automatique (taux perso/type de shift, durée - pause).
         Un ajustement ici gagne toujours sur le calcul automatique, y compris
         après un "Recalculer" du rapport de salaire (qui re-somme les shifts,
         donc relit cet ajustement plutôt que de l'écraser). ── -->
    <details class="form-advanced">
        <summary><?= __('shift_manual_override') ?></summary>
        <p class="form-hint"><?= __('shift_manual_override_hint') ?></p>
        <div class="form-row">
            <div class="form-group">
                <label class="form-label"><?= __('shift_hourly_rate_override') ?></label>
                <input type="number" name="hourly_rate_override" class="form-control" step="0.01" min="0"
                       placeholder="<?= __('automatic') ?>"
                       value="<?= ($shift['hourly_rate_override'] ?? null) !== null ? htmlspecialchars((string) $shift['hourly_rate_override']) : '' ?>">
            </div>
            <div class="form-group">
                <label class="form-label"><?= __('shift_net_minutes_override') ?></label>
                <input type="number" name="net_minutes_override" class="form-control" step="1" min="0"
                       placeholder="<?= __('automatic') ?>"
                       value="<?= ($shift['net_minutes_override'] ?? null) !== null ? htmlspecialchars((string) $shift['net_minutes_override']) : '' ?>">
            </div>
        </div>
    </details>

    <!-- ── Notes ─────────────────────────────────────────────── -->
    <div class="form-group">
        <label class="form-label"><?= __('notes') ?></label>
        <textarea name="notes" class="form-control" rows="2"
                  placeholder="<?= __('notes_placeholder') ?>"><?= htmlspecialchars($shift['notes'] ?? '') ?></textarea>
    </div>

    <!-- ── Shift ouvert (bourse aux shifts) ──────────────────── -->
    <?php if ($feat('open_shifts')): ?>
    <div class="form-group">
        <label class="form-label"><?= __('open_shift_note_label') ?></label>
        <textarea name="open_shift_note" class="form-control" rows="2"
                  placeholder="<?= __('open_shift_note_placeholder') ?>"><?= htmlspecialchars($shift['open_shift_note'] ?? '') ?></textarea>
    </div>
    <div class="form-group">
        <label class="form-label"><?= __('open_shifts') ?></label>
        <label class="toggle-label">
            <input type="checkbox" name="is_open" value="1" <?= (int)($shift['is_open'] ?? 0) ? 'checked' : '' ?>>
            <?= __('publish_to_bourse') ?>
        </label>
    </div>
    <?php endif; ?>
</div>
