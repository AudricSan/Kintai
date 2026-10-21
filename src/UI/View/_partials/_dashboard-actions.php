<?php
/**
 * Actions de l'en-tête d'un tableau de bord : « Actualiser » (force le rafraîchissement des widgets qui en ont un),
 * « Activer le mode mural » (plein écran sans barres, pour un écran de magasin) et l'indication de la dernière mise à
 * jour. Comportement dans dashboard-live.js. Le bouton « Quitter » du mode mural n'est visible que dans ce mode.
 */
?>
<span class="dash-updated text-xs text-muted" data-dash-updated aria-live="polite"
      data-label-now="<?= htmlspecialchars(__('dashboard_updated_now')) ?>"
      data-label-ago="<?= htmlspecialchars(__('dashboard_updated_ago')) ?>"
      data-label-expired="<?= htmlspecialchars(__('dashboard_session_expired')) ?>"></span>
<button type="button" class="btn btn--ghost btn--sm" data-dash-refresh-now><?= __('dashboard_refresh_now') ?></button>
<button type="button" class="btn btn--ghost btn--sm" data-dash-wall aria-pressed="false"><?= __('dashboard_wall_enter') ?></button>
<button type="button" class="btn btn--primary btn--sm dash-wall-exit" data-dash-wall-exit hidden><?= __('dashboard_wall_exit') ?></button>