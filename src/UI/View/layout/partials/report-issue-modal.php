<?php
/* Modale "Signaler un problème" — déclenchée depuis le footer applicatif
 * (voir partials/_footer.php, bouton onclick="riOpen()"). Crée directement
 * une issue sur le dépôt GitHub du projet (voir SupportController).
 * Variables disponibles via ViewRenderer::share() : $BASE_URL
 */
?>

<div id="ri-overlay" class="fb-overlay" onclick="riClose()" role="dialog" aria-modal="true" aria-labelledby="ri-modal-title">
    <div class="fb-modal" onclick="event.stopPropagation()">

        <div class="fb-modal-header">
            <strong id="ri-modal-title"><?= __('report_issue_modal_title') ?></strong>
            <button type="button" class="fb-modal-close" onclick="riClose()" aria-label="<?= __('close') ?>">×</button>
        </div>

        <div class="fb-modal-body">

            <?php
            $riError   = $_GET['ri_error']   ?? null;
            $riSuccess = $_GET['ri_success'] ?? null;
            ?>
            <?php if ($riSuccess !== null): ?>
                <div class="alert alert--success mb-sm">
                    <?= __('report_issue_success') ?>
                    <a href="<?= htmlspecialchars($riSuccess, ENT_QUOTES) ?>" target="_blank" rel="noopener noreferrer"><?= __('report_issue_view_issue') ?></a>
                </div>
            <?php elseif ($riError !== null): ?>
                <div class="alert alert--error mb-sm">
                    <?php if ($riError === 'empty'): ?>
                        <?= __('report_issue_error_empty') ?>
                    <?php else: ?>
                        <?= __('report_issue_error_generic') ?>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <p class="form-hint mb-sm"><?= __('report_issue_privacy_note') ?></p>

            <form method="POST" action="<?= route_url('support.report_issue') ?>" id="ri-form" class="form-stack">
                <?= csrf_field() ?>
                <input type="hidden" name="return_to" value="<?= htmlspecialchars(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/') ?>">

                <div class="form-group">
                    <label class="form-label form-label--required" for="ri-title">
                        <?= __('report_issue_title_label') ?>
                    </label>
                    <input type="text" name="title" id="ri-title" class="form-control"
                           maxlength="200" required
                           placeholder="<?= __('report_issue_title_placeholder') ?>">
                </div>

                <div class="form-group">
                    <label class="form-label form-label--required" for="ri-description">
                        <?= __('report_issue_description_label') ?>
                    </label>
                    <textarea name="description" id="ri-description" class="form-control"
                              rows="5" required maxlength="4000"
                              placeholder="<?= __('report_issue_description_placeholder') ?>"></textarea>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn--primary"><?= __('report_issue_submit') ?></button>
                    <button type="button" class="btn btn--ghost" onclick="riClose()"><?= __('cancel') ?></button>
                </div>
            </form>
        </div>
    </div>
</div>

<script type="application/json" id="kintai-report-issue-data"><?= json_encode([
    'autoOpen' => ($riError !== null || $riSuccess !== null),
], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?></script>
<script src="<?= $BASE_URL ?>/assets/js/modules/report-issue.js?v=<?= asset_version() ?>"></script>
