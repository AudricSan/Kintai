<?php
use kintai\UI\Components\Badge;
use kintai\UI\Components\Button;

/**
 * Repli : boutons « Publier / Fermer la bourse » de la page d'édition d'un shift, tant que shift-claim actif ne
 * déclare pas sa section du formulaire de shift (contrat 1.17). Ils sont dans le formulaire du shift : formaction vers
 * la route du bundle (un <form> imbriqué était ignoré par le navigateur, le bouton enregistrait alors le shift).
 *
 * @var array  $shift
 * @var string $BASE_URL
 */
$_shiftUrl = $BASE_URL . '/admin/shifts/' . (int) $shift['id'];
?>
<?php if (!(int) ($shift['is_open'] ?? 0)): ?>
    <?= Button::make(__('publish_to_bourse'))->warning()->submit()->attrs(['formaction' => $_shiftUrl . '/publish', 'formmethod' => 'post', 'formnovalidate' => 'formnovalidate'])->render() ?>
<?php else: ?>
    <?= Button::make(__('close_bourse'))->ghost()->submit()->attrs(['formaction' => $_shiftUrl . '/unpublish', 'formmethod' => 'post', 'formnovalidate' => 'formnovalidate'])->render() ?>
    <?= Badge::make(__('open_bourse'))->success()->render() ?>
<?php endif; ?>
