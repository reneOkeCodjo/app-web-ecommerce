/**
 * Messages temporaires : fermeture manuelle et disparition automatique.
 *
 * Chaque message possède un bouton de fermeture. Sans action de
 * l'utilisateur, il disparaît tout seul après quelques secondes.
 */

(function () {
  "use strict";

  // Durée d'affichage en millisecondes (6 secondes).
  const LIFETIME = 6000;

  /**
   * Retire un message de la page avec une petite transition.
   * @param {HTMLElement} toast
   */
  function dismiss(toast) {
    toast.classList.add("toast--leaving");
    window.setTimeout(() => toast.remove(), 220);
  }

  // On parcourt tous les messages présents au chargement.
  document.querySelectorAll("[data-toast]").forEach((toast) => {
    if (!(toast instanceof HTMLElement)) {
      return;
    }

    // Bouton de fermeture.
    const close = toast.querySelector("[data-toast-close]");
    if (close instanceof HTMLButtonElement) {
      close.addEventListener("click", () => dismiss(toast));
    }

    // Disparition automatique.
    window.setTimeout(() => dismiss(toast), LIFETIME);
  });
})();
