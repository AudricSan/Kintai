<?php
use kintai\UI\Components\Flash;

/** @var array  $submissions */
/** @var array  $submissionImages */
/** @var array  $storeNames */
/** @var array  $availableStores */
/** @var int    $filterStoreId */
/** @var string $BASE_URL */

echo Flash::fromQuery('success', [
    'created' => __('photo_created'),
    'deleted' => __('photo_deleted'),
])->render();
?>
<div class="page-header">
    <h2 class="page-header__title"><?= __('photos') ?> <span class="page-count">(<?= count($submissions) ?>)</span></h2>
    <div class="page-header__actions">
        <a href="<?= $BASE_URL ?>/admin/photos/create" class="btn btn--primary">+ <?= __('photo_new_submission') ?></a>
    </div>
</div>

<div class="card mb-sm">
    <div class="card-body">
        <form method="GET" action="" class="form-flex">
            <div class="form-group">
                <label class="form-label"><?= __('store') ?></label>
                <select name="store_id" class="form-control form-control--sm" onchange="this.form.submit()">
                    <option value="0"><?= __('all') ?></option>
                    <?php foreach ($availableStores as $s): ?>
                        <option value="<?= (int) $s['id'] ?>" <?= $filterStoreId === (int) $s['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($s['name'] ?? $storeNames[(int) $s['id']] ?? '#' . $s['id']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <a href="?" class="btn btn--ghost btn--sm"><?= __('reset') ?></a>
            </div>
        </form>
    </div>
</div>

<?php if (empty($submissions)): ?>
    <div class="card">
        <div class="empty-state"><?= __('photo_empty') ?></div>
    </div>
<?php else: ?>
    <div class="photo-grid" id="photo-grid">
        <?php foreach ($submissions as $sub): ?>
            <?php
            $sid     = (int) $sub['id'];
            $images  = $submissionImages[$sid] ?? [];
            $storeId = (int) ($sub['store_id'] ?? 0);
            $storeName = $storeNames[$storeId] ?? '#' . $storeId;
            $preview = !empty($images) ? $images[0] : null;
            $count   = count($images);
            ?>
            <a href="<?= $BASE_URL ?>/admin/photos/<?= $storeId ?>/<?= $sid ?>" class="card photo-card">
                <div class="photo-card__preview">
                    <?php if ($preview): ?>
                        <img src="<?= $BASE_URL ?>/<?= htmlspecialchars($preview['filepath']) ?>"
                             alt="<?= htmlspecialchars($preview['filename']) ?>"
                             loading="lazy"
                             style="width:100%;height:160px;object-fit:scale-down;background:#e9ecef;border-radius:var(--radius) var(--radius) 0 0">
                    <?php else: ?>
                        <div class="empty-state" style="height:160px;display:flex;align-items:center;justify-content:center"><?= __('photo_no_images') ?></div>
                    <?php endif; ?>
                </div>
                <div class="card-body" style="padding:var(--space-3)">
                    <div class="photo-card__meta">
                        <strong><?= htmlspecialchars($storeName) ?></strong>
                        <span class="badge badge--secondary badge--sm"><?= $count ?> <?= __('photos') ?></span>
                    </div>
                    <div class="text-muted text-sm"><?= htmlspecialchars($sub['week_label'] ?? '') ?></div>
                    <?php if (!empty($sub['notes'])): ?>
                        <div class="text-muted text-xs" style="margin-top:var(--space-1)"><?= htmlspecialchars(mb_substr($sub['notes'], 0, 80)) ?></div>
                    <?php endif; ?>
                    <div class="text-xs text-muted" style="margin-top:var(--space-1)">
                        <?= date('d/m/Y', strtotime($sub['created_at'] ?? 'now')) ?>
                    </div>
                </div>
            </a>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
