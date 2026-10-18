/**
 * Ouverture / fermeture des `<dialog>` « Ajouter un produit » et « Ajouter un utilisateur ».
 * Un clic sur le fond (`event.target === dialog`) les ferme aussi.
 */

/**
 * Ferme une dialog lorsque l'utilisateur clique sur son fond (pas sur le contenu).
 * @param {HTMLDialogElement} dialog
 * @param {MouseEvent} event
 * @returns {void}
 */
function closeOnBackdropClick(dialog, event) {
    if (event.target === dialog) {
        dialog.close();
    }
}

/**
 * Relie les gestionnaires ouvrir / annuler / fermer au fond pour une dialog de création.
 * @param {Object} config
 * @param {HTMLElement|null} config.openBtn - Bouton qui ouvre la dialog
 * @param {HTMLDialogElement|null} config.dialog
 * @param {HTMLElement|null} [config.cancelBtn] - Bouton d'annulation optionnel
 * @returns {void}
 */
function initCreateDialog({ openBtn, dialog, cancelBtn }) {
    if (!openBtn || !dialog) return;

    openBtn.addEventListener('click', () => {
        dialog.showModal();
    });

    cancelBtn?.addEventListener('click', () => {
        dialog.close();
    });

    dialog.addEventListener('click', (event) => closeOnBackdropClick(dialog, event));
}

// --- Ajouter un produit ---
initCreateDialog({
    openBtn: document.querySelector('#add-product-btn'),
    dialog: document.querySelector('#add-product-dialog'),
    cancelBtn: document.querySelector('#cancel-product-btn'),
});

// --- Ajouter un utilisateur ---
initCreateDialog({
    openBtn: document.querySelector('#add-user-btn'),
    dialog: document.querySelector('#add-user-dialog'),
    cancelBtn: document.querySelector('#cancel-user-btn'),
});
