<?php
/** @var string $mode 'create'|'edit' */
/** @var array  $shift */
/** @var array  $all_users */
/** @var array  $all_stores */
/** @var array  $all_types */
$action = $mode === 'edit'
    ? $BASE_URL . '/admin/shifts/' . (int) $shift['id'] . '/edit'
    : $BASE_URL . '/admin/shifts/create';
?>

<div class="page-header">
    <h2 class="page-header__title"><?= $mode === 'edit' ? __('edit_shift') : __('new_shift') ?></h2>
    <div class="page-header__actions">
        <a href="<?= $BASE_URL ?>/admin/shifts" class="btn btn--ghost">← <?= __('back') ?></a>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <form method="POST" action="<?= htmlspecialchars($action) ?>">
            <div class="form-stack">

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

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label"><?= __('pause') ?> (minutes)</label>
                        <input type="number" name="pause_minutes" class="form-control" min="0" max="120"
                               value="<?= (int) ($shift['pause_minutes'] ?? 0) ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label"><?= __('notes') ?></label>
                    <textarea name="notes" class="form-control" rows="2"
                              placeholder="<?= __('notes_placeholder') ?>"><?= htmlspecialchars($shift['notes'] ?? '') ?></textarea>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn--primary">
                        <?= $mode === 'edit' ? __('save') : __('new_shift') ?>
                    </button>
                    <a href="<?= $BASE_URL ?>/admin/shifts" class="btn btn--ghost"><?= __('cancel') ?></a>
                </div>

            </div>
        </form>
    </div>
</div>
