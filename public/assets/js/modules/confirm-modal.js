/**
 * Modale de confirmation générique — remplace le confirm() natif du navigateur
 * pour tout formulaire marqué data-confirm="message". Un seul dialogue partagé
 * (layout/partials/_confirm-modal.php), inclus une fois pour toute l'app.
 *
 * Interception au clic sur le bouton submit (pas sur l'événement submit du
 * formulaire) : plus robuste, évite toute subtilité liée à la phase de
 * capture/bulle de l'événement submit natif selon la structure DOM de la page.
 */
(function () {
    'use strict';

    var MODAL_ID = 'global-confirm-modal';
    var pendingForm = null;

    document.addEventListener('click', function (e) {
        var submitter = e.target.closest('button[type="submit"], input[type="submit"]');
        if (!submitter) return;

        var form = submitter.closest('form[data-confirm]');
        if (!form) return;

        e.preventDefault();
        e.stopPropagation();
        pendingForm = form;

        var msgEl = document.getElementById('global-confirm-message');
        if (msgEl) msgEl.textContent = form.getAttribute('data-confirm') || '';

        if (window.openModal) window.openModal(MODAL_ID);
    }, true);

    document.addEventListener('click', function (e) {
        if (e.target.id !== 'global-confirm-submit-btn') return;

        if (window.closeModal) window.closeModal(MODAL_ID);
        if (pendingForm) {
            var form = pendingForm;
            pendingForm = null;
            if (form.requestSubmit) form.requestSubmit();
            else form.submit();
        }
    });
})();
