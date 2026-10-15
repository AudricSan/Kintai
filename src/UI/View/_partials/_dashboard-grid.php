<?php
/**
 * Grille des widgets d'un tableau de bord, dans l'ordre et la largeur choisis par l'utilisateur (DashboardLayout).
 * Le bouton « Personnaliser » passe la grille en mode édition (dashboard-layout.js) : chaque widget reçoit une barre
 * d'outils pour le glisser-déposer, le monter/descendre, changer sa largeur et le retirer ; chaque changement est
 * enregistré.
 *
 * @var array<string, string>                                  $_dashBlocks HTML de chaque widget (clé => html)
 * @var array{order: list<string>, widths: array<string,string>} $_dashLayout
 * @var array<string, string>                                  $_dashLabels Libellés des widgets
 * @var string                                                 $_dashSaveUrl    Disposition (ordre, largeur)
 * @var string                                                 $_dashWidgetsUrl Ajout / retrait d'un widget
 */
use kintai\Core\Extension\DashboardLayout;
?>
<div class="dash-grid" data-dash-grid data-save-url="<?= htmlspecialchars($_dashSaveUrl) ?>"
     data-widgets-url="<?= htmlspecialchars($_dashWidgetsUrl ?? '') ?>"
     data-label-half="<?= htmlspecialchars(__('dashboard_widget_make_half')) ?>"
     data-label-full="<?= htmlspecialchars(__('dashboard_widget_make_full')) ?>"
     data-label-error="<?= htmlspecialchars(__('dashboard_layout_save_failed')) ?>">
    <?php foreach ($_dashLayout['order'] as $_dk): ?>
        <?php if (trim((string) ($_dashBlocks[$_dk] ?? '')) === '') continue; ?>
        <?php $_dw = ($_dashLayout['widths'][$_dk] ?? DashboardLayout::FULL) === DashboardLayout::HALF ? DashboardLayout::HALF : DashboardLayout::FULL; ?>
        <?php $_dl = (string) ($_dashLabels[$_dk] ?? $_dk); ?>
        <section id="dash-widget-<?= htmlspecialchars($_dk) ?>" class="dash-grid__item dash-grid__item--<?= $_dw ?>" data-widget-label="<?= htmlspecialchars($_dl) ?>" data-widget-key="<?= htmlspecialchars($_dk) ?>" data-widget-width="<?= $_dw ?>">
            <div class="dash-grid__tools" role="toolbar" aria-label="<?= htmlspecialchars($_dl) ?>">
                <button type="button" class="dash-grid__handle" data-dash-handle
                        title="<?= htmlspecialchars(__('dashboard_widget_drag')) ?>"
                        aria-label="<?= htmlspecialchars(__('dashboard_widget_drag') . ' — ' . $_dl) ?>">⠿</button>
                <span class="dash-grid__name"><?= htmlspecialchars($_dl) ?></span>
                <button type="button" class="btn btn--ghost btn--sm" data-dash-move="up"
                        aria-label="<?= htmlspecialchars(__('dashboard_widget_up') . ' — ' . $_dl) ?>">↑</button>
                <button type="button" class="btn btn--ghost btn--sm" data-dash-move="down"
                        aria-label="<?= htmlspecialchars(__('dashboard_widget_down') . ' — ' . $_dl) ?>">↓</button>
                <button type="button" class="btn btn--ghost btn--sm" data-dash-width>
                    <?= htmlspecialchars($_dw === DashboardLayout::HALF ? __('dashboard_widget_make_full') : __('dashboard_widget_make_half')) ?>
                </button>
                <button type="button" class="btn btn--ghost btn--sm dash-grid__remove" data-dash-remove
                        title="<?= htmlspecialchars(__('dashboard_widget_remove')) ?>"
                        aria-label="<?= htmlspecialchars(__('dashboard_widget_remove') . ' — ' . $_dl) ?>">✕</button>
            </div>
            <?= $_dashBlocks[$_dk] ?>
        </section>
    <?php endforeach; ?>
</div>
<script src="<?= $BASE_URL ?>/assets/js/modules/dashboard-layout.js"></script>
<?php unset($_dk, $_dw, $_dl); ?>
