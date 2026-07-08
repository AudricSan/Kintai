<?php
/* Modale feedback — incluse dans layout/app.php côté employé uniquement.
 * Variables disponibles via ViewRenderer::share() : $BASE_URL, $auth_user
 */
?>

<!-- Bouton footer feedback -->
<footer class="app-footer">
    <button type="button" class="fb-footer-btn" onclick="fbOpen()" aria-haspopup="dialog">
        <?= __('send_feedback') ?>
    </button>
</footer>

<!-- Overlay + modale -->
<div id="fb-overlay" class="fb-overlay" onclick="fbClose()" role="dialog" aria-modal="true" aria-labelledby="fb-modal-title">
    <div class="fb-modal" onclick="event.stopPropagation()">

        <div class="fb-modal-header">
            <strong id="fb-modal-title"><?= __('feedback_modal_title') ?></strong>
            <button type="button" class="fb-modal-close" onclick="fbClose()" aria-label="<?= __('close') ?>">×</button>
        </div>

        <div class="fb-modal-body">

            <?php
            // Affiche les messages flash transmis par le contrôleur
            $fbError   = $_GET['fb_error']   ?? null;
            $fbSuccess = $_GET['fb_success']  ?? null;
            ?>
            <?php if ($fbSuccess === 'sent'): ?>
                <div class="alert alert--success mb-sm"><?= __('feedback_sent') ?></div>
            <?php elseif ($fbError !== null): ?>
                <div class="alert alert--error mb-sm">
                    <?php if ($fbError === 'duplicate'): ?>
                        <?= __('feedback_error_duplicate') ?>
                    <?php elseif ($fbError === 'empty_message'): ?>
                        <?= __('feedback_error_empty') ?>
                    <?php else: ?>
                        <?= __('error') ?>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="<?= route_url('employee.feedback.submit') ?>" id="fb-form" class="form-stack">
                <?= csrf_field() ?>
                <input type="hidden" name="return_to" value="<?= htmlspecialchars(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/') ?>">

                <!-- Catégorie -->
                <div class="form-group">
                    <label class="form-label form-label--required" for="fb-category">
                        <?= __('feedback_category') ?>
                    </label>
                    <select name="category" id="fb-category" class="form-control" required onchange="fbCategoryChange(this.value)">
                        <option value="other"><?= __('feedback_cat_other') ?></option>
                        <option value="shift"><?= __('feedback_cat_shift') ?></option>
                        <option value="schedule"><?= __('feedback_cat_schedule') ?></option>
                        <option value="app"><?= __('feedback_cat_app') ?></option>
                    </select>
                </div>

                <!-- Shift (conditionnel) -->
                <div class="form-group hidden" id="fb-shift-group">
                    <label class="form-label form-label--required" for="fb-shift-select">
                        <?= __('feedback_select_shift') ?>
                    </label>
                    <select name="shift_id" id="fb-shift-select" class="form-control">
                        <option value=""><?= __('loading') ?>…</option>
                    </select>
                    <span class="form-hint" id="fb-shift-hint"></span>
                </div>

                <!-- Note (étoiles) -->
                <div class="form-group">
                    <label class="form-label"><?= __('feedback_rating') ?> <span class="form-hint">(<?= __('optional') ?>)</span></label>
                    <div class="fb-star-picker" id="fb-star-picker">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <button type="button"
                                    class="fb-star-btn"
                                    data-value="<?= $i ?>"
                                    onclick="fbSetRating(<?= $i ?>)"
                                    aria-label="<?= $i ?>/5">★</button>
                        <?php endfor; ?>
                        <button type="button" class="fb-star-clear" onclick="fbClearRating()" title="<?= __('reset') ?>">✕</button>
                    </div>
                    <input type="hidden" name="rating" id="fb-rating-input" value="">
                </div>

                <!-- Message -->
                <div class="form-group">
                    <label class="form-label form-label--required" for="fb-message">
                        <?= __('feedback_message') ?>
                    </label>
                    <textarea name="message" id="fb-message" class="form-control"
                              rows="4" required maxlength="2000"
                              placeholder="<?= __('feedback_message_placeholder') ?>"></textarea>
                </div>

                <!-- Anonymat -->
                <div class="form-group fb-anon-row">
                    <label class="fb-checkbox-label">
                        <input type="checkbox" name="anonymous" value="1" id="fb-anonymous">
                        <?= __('feedback_anonymous') ?>
                    </label>
                    <span class="form-hint"><?= __('feedback_anonymous_hint') ?></span>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn--primary"><?= __('send') ?></button>
                    <button type="button" class="btn btn--ghost" onclick="fbClose()"><?= __('cancel') ?></button>
                </div>
            </form>
        </div>
    </div>
</div>

<script type="application/json" id="kintai-feedback-data"><?= json_encode([
    'baseUrl'  => $BASE_URL,
    'autoOpen' => ($fbError !== null || $fbSuccess !== null),
    'i18n'     => [
        'selectShiftPlaceholder' => __('feedback_select_shift_placeholder'),
        'noPastShift'            => __('feedback_no_past_shift'),
        'networkError'           => __('network_error'),
    ],
], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?></script>
<script src="<?= $BASE_URL ?>/assets/js/modules/feedback.js"></script>
