<?php
/**
 * Repli : champs « Shift ouvert » de la bourse aux shifts, affichés par le Core tant que shift-claim actif ne déclare
 * pas sa section du formulaire de shift (contrat 1.17). Disparaîtra avec l'extraction de shift-claim.
 *
 * @var array $shift
 */
?>
    <div class="form-group">
        <label class="form-label"><?= __('open_shift_note_label') ?></label>
        <textarea name="open_shift_note" class="form-control" rows="2"
                  placeholder="<?= __('open_shift_note_placeholder') ?>"><?= htmlspecialchars($shift['open_shift_note'] ?? '') ?></textarea>
    </div>
    <div class="form-group">
        <label class="form-label"><?= __('open_shifts') ?></label>
        <label class="toggle-label">
            <input type="hidden" name="is_open" value="0">
            <input type="checkbox" name="is_open" value="1" <?= (int)($shift['is_open'] ?? 0) ? 'checked' : '' ?>>
            <?= __('publish_to_bourse') ?>
        </label>
    </div>
