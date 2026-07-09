<?php
/** @var array  $submission */
/** @var array  $images */
/** @var array|null $store */
/** @var string $BASE_URL */
/** @var array|null $auth_user */

$storeName = $store['name'] ?? ('#' . ($submission['store_id'] ?? '?'));
$sid       = (int) $submission['id'];
$isOwner   = !empty($auth_user['is_admin']);
?>
<div class="page-header">
    <h2 class="page-header__title">
        <?= htmlspecialchars($storeName) ?> — <?= htmlspecialchars($submission['week_label'] ?? '') ?>
    </h2>
    <div class="page-header__actions">
        <a href="<?= $BASE_URL ?>/admin/photos" class="btn btn--ghost btn--sm">← <?= __('back') ?></a>
        <?php if ($isOwner): ?>
            <form method="POST" action="<?= $BASE_URL ?>/admin/photos/<?= (int) ($submission['store_id'] ?? 0) ?>/<?= $sid ?>/delete"
                  class="form-inline" onsubmit="return confirm('<?= __('photo_confirm_delete') ?>')">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn--danger btn--sm"><?= __('delete') ?></button>
            </form>
        <?php endif; ?>
    </div>
</div>

<?php if (!empty($submission['notes'])): ?>
    <div class="card card--mb">
        <div class="card-body">
            <strong><?= __('photo_notes') ?> :</strong>
            <p class="text-sm mt-xs"><?= nl2br(htmlspecialchars($submission['notes'])) ?></p>
        </div>
    </div>
<?php endif; ?>

<?php if (empty($images)): ?>
    <div class="card">
        <div class="empty-state"><?= __('photo_no_images') ?></div>
    </div>
<?php else: ?>
    <div class="photo-detail-grid">
        <?php foreach (array_values($images) as $i => $img): ?>
            <div class="card photo-detail-card">
                <button type="button" class="photo-detail-card__trigger" onclick="photoCarouselOpen(<?= (int) $i ?>)">
                    <img src="<?= $BASE_URL ?>/<?= htmlspecialchars($img['filepath']) ?>"
                         alt="<?= htmlspecialchars($img['filename']) ?>"
                         loading="lazy"
                         class="photo-detail-card__img">
                </button>
                <div class="card-body photo-detail-card__body">
                    <div class="text-xs text-muted"><?= htmlspecialchars($img['filename']) ?></div>
                    <?php if (!empty($img['filesize'])): ?>
                        <div class="text-xs text-muted"><?= number_format((int) $img['filesize'] / 1024, 1) ?> KB</div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- ── Carousel photo (visionneuse plein écran) ─────────────────────── -->
    <div id="photo-carousel-overlay" class="modal photo-carousel" onclick="photoCarouselClose(event)">
        <button type="button" class="photo-carousel__close" onclick="photoCarouselClose(event)">&times;</button>
        <button type="button" class="photo-carousel__nav photo-carousel__nav--prev" onclick="photoCarouselPrev(event)">&lsaquo;</button>
        <div class="photo-carousel__stage" onclick="event.stopPropagation()">
            <img id="photo-carousel-img" class="photo-carousel__img" src="" alt="">
            <div class="photo-carousel__caption">
                <span id="photo-carousel-filename"></span>
                <span id="photo-carousel-counter" class="photo-carousel__counter"></span>
            </div>
        </div>
        <button type="button" class="photo-carousel__nav photo-carousel__nav--next" onclick="photoCarouselNext(event)">&rsaquo;</button>
    </div>

    <script type="application/json" id="photo-carousel-data"><?= json_encode(array_map(static function ($img) use ($BASE_URL) {
        return [
            'src'      => $BASE_URL . '/' . $img['filepath'],
            'filename' => $img['filename'],
        ];
    }, array_values($images)), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?></script>
    <script src="<?= $BASE_URL ?>/assets/js/modules/photo-carousel.js"></script>
<?php endif; ?>
