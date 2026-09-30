<?php
/* Modale feedback — déclenchée depuis le footer applicatif (voir
 * partials/_footer.php, bouton data-on-click="fbOpen").
 * Variables disponibles via ViewRenderer::share() : $BASE_URL, $auth_user
 */
?>
<?php if ($feedbackCss = bundle_asset('feedback', 'css/feedback.css')): ?>
<link rel="stylesheet" href="<?= $feedbackCss ?>">
<?php endif; ?>

<!-- Overlay + modale -->
<div id="fb-overlay" class="fb-overlay" data-on-click="fbClose" role="dialog" aria-modal="true" aria-labelledby="fb-modal-title">
    <div class="fb-modal" data-stop-propagation>

        <div class="fb-modal-header">
            <strong id="fb-modal-title"><?= __('feedback_modal_title') ?></strong>
            <button type="button" class="fb-modal-close" data-on-click="fbClose" aria-label="<?= __('close') ?>">×</button>
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
                    <select name="category" id="fb-category" class="form-control" required data-on-change="fbCategoryChange" data-args='["@value"]'>
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
                                    data-on-click="fbSetRating" data-args="[<?= (int) $i ?>]"
                                    aria-label="<?= $i ?>/5">★</button>
                        <?php endfor; ?>
                        <button type="button" class="fb-star-clear" data-on-click="fbClearRating" title="<?= __('reset') ?>">✕</button>
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
                    <button type="button" class="btn btn--ghost" data-on-click="fbClose"><?= __('cancel') ?></button>
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
<?php if ($feedbackJs = bundle_asset('feedback', 'js/feedback.js')): ?>
<script src="<?= $feedbackJs ?>"></script>
<?php endif; ?>
