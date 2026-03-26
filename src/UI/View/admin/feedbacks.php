<?php
/** @var array $feedbacks */
/** @var array $users_map */
/** @var array $stores_map */
/** @var array $shifts_map */
/** @var array $shift_types_map */
/** @var array $filterable_stores */
/** @var int   $filter_store_id */
?>

<div class="page-header">
    <h2 class="page-header__title">
        <?= __('feedbacks') ?>
        <span class="page-count">(<?= count($feedbacks) ?>)</span>
    </h2>
</div>

<?php if (isset($_GET['success'])): ?>
    <div class="alert alert--success mb-sm"><?= __('feedback_deleted') ?></div>
<?php elseif (isset($_GET['error'])): ?>
    <div class="alert alert--error mb-sm"><?= __('error') ?></div>
<?php endif; ?>

<?php if (count($filterable_stores) > 1): ?>
    <form method="GET" class="form-filter mb-sm">
        <label class="form-label m-0"><?= __('store') ?></label>
        <select name="store_id" class="form-control form-control-sm" onchange="this.form.submit()">
            <option value="0"><?= __('all_stores') ?></option>
            <?php foreach ($filterable_stores as $s): ?>
                <option value="<?= (int) $s['id'] ?>"<?= (int) $s['id'] === $filter_store_id ? ' selected' : '' ?>>
                    <?= htmlspecialchars($s['name'] ?? '') ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php if ($filter_store_id > 0): ?>
            <a href="?" class="btn btn--ghost btn--sm"><?= __('reset') ?></a>
        <?php endif; ?>
    </form>
<?php endif; ?>

<div class="card">
    <?php if (empty($feedbacks)): ?>
        <div class="empty-state"><?= __('no_feedback') ?></div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th><?= __('date_hour') ?></th>
                        <th><?= __('store') ?></th>
                        <th><?= __('employee') ?></th>
                        <th><?= __('feedback_category') ?></th>
                        <th><?= __('feedback_rating') ?></th>
                        <th><?= __('feedback_message') ?></th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($feedbacks as $fb): ?>
                        <?php
                        $fbId       = (int) ($fb['id'] ?? 0);
                        $fbUserId   = (int) ($fb['user_id'] ?? 0);
                        $fbAnon     = (bool) ($fb['anonymous'] ?? false);
                        $fbCat      = $fb['category'] ?? 'other';
                        $fbRating   = $fb['rating'] ?? null;
                        $fbShiftId  = (int) ($fb['shift_id'] ?? 0);
                        $fbShift    = $fbShiftId > 0 ? ($shifts_map[$fbShiftId] ?? null) : null;
                        $fbTypeName = $fbShift ? ($shift_types_map[(int) ($fbShift['shift_type_id'] ?? 0)] ?? '—') : null;

                        $authorIsAnon = $fbAnon;
                        $authorName   = $fbAnon ? '' : ($users_map[$fbUserId] ?? ('#' . $fbUserId));

                        $categoryLabel = match($fbCat) {
                            'shift'    => __('feedback_cat_shift'),
                            'schedule' => __('feedback_cat_schedule'),
                            'app'      => __('feedback_cat_app'),
                            default    => __('feedback_cat_other'),
                        };
                        $categoryColor = match($fbCat) {
                            'shift'    => 'primary',
                            'schedule' => 'warning',
                            'app'      => 'admin',
                            default    => 'inactive',
                        };
                        ?>
                        <tr>
                            <td class="td-nowrap text-sm-muted">
                                <?= htmlspecialchars($fb['created_at'] ?? '') ?>
                            </td>
                            <td class="text-sm-muted">
                                <?= htmlspecialchars($stores_map[(int) ($fb['store_id'] ?? 0)] ?? '—') ?>
                            </td>
                            <td>
                                <?php if ($authorIsAnon): ?>
                                    <em class="text-dim"><?= __('feedback_anonymous_author') ?></em>
                                <?php else: ?>
                                    <?= htmlspecialchars($authorName) ?>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge badge--<?= $categoryColor ?>">
                                    <?= $categoryLabel ?>
                                </span>
                                <?php if ($fbShift): ?>
                                    <div class="text-hint mt-xs">
                                        <?= htmlspecialchars($fbShift['shift_date'] ?? '') ?>
                                        <?= htmlspecialchars($fbTypeName ?? '') ?>
                                        <?= htmlspecialchars(substr($fbShift['start_time'] ?? '', 0, 5)) ?>–<?= htmlspecialchars(substr($fbShift['end_time'] ?? '', 0, 5)) ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($fbRating !== null): ?>
                                    <span class="fb-stars" title="<?= (int)$fbRating ?>/5">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <span class="fb-star<?= $i <= (int)$fbRating ? ' fb-star--on' : '' ?>">★</span>
                                        <?php endfor; ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-dim">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="fb-message-cell">
                                <?= nl2br(htmlspecialchars($fb['message'] ?? '')) ?>
                            </td>
                            <td>
                                <form method="POST"
                                      action="<?= $BASE_URL ?>/admin/feedbacks/<?= $fbId ?>/delete"
                                      onsubmit="return confirm('<?= __('feedback_delete_confirm') ?>')">
                                    <button type="submit" class="btn btn--danger btn--sm">
                                        <?= __('delete') ?>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
