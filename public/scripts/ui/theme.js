/*
  theme.js
  ----------------
  Gère le bouton qui permet de passer du thème sombre au thème clair.
*/

(function () {
  // On attend que le HTML soit prêt avant de chercher les boutons.
  document.addEventListener("DOMContentLoaded", function () {
    // On récupère tous les boutons de changement de thème de la page.
    var toggles = document.querySelectorAll("[data-theme-toggle]");

    // On prépare une fonction qui met à jour le texte d'accessibilité du bouton.
    function syncLabel() {
      // On lit le thème actuellement appliqué.
      var current = document.documentElement.getAttribute("data-theme") || "dark";

      // On parcourt chaque bouton pour mettre à jour son libellé.
      toggles.forEach(function (button) {
        // Si on est en sombre, cliquer passera en clair, et inversement.
        button.setAttribute(
          "aria-label",
          current === "light" ? "Passer au thème sombre" : "Passer au thème clair"
        );
      });
    }

    // On applique une première fois le bon libellé.
    syncLabel();

    // On ajoute l'action de clic sur chaque bouton.
    toggles.forEach(function (button) {
      button.addEventListener("click", function () {
        // On lit le thème actuel.
        var current = document.documentElement.getAttribute("data-theme") || "dark";

        // On calcule le thème opposé.
        var next = current === "light" ? "dark" : "light";

        // On applique le nouveau thème à toute la page.
        document.documentElement.setAttribute("data-theme", next);

        // On mémorise le choix pour les prochaines visites.
        try {
          localStorage.setItem("sv-theme", next);
        } catch (error) {
          // Si le stockage est indisponible, le thème reste actif pour cette page.
        }

        // On met à jour le libellé du bouton.
        syncLabel();
      });
    });
  });
})();
