/*
  theme-init.js
  ----------------
  Ce petit script s'exécute très tôt, avant l'affichage de la page.
  Il choisit le thème (sombre ou clair) pour éviter un flash de couleur.
*/

(function () {
  // On protège le code avec try/catch : si localStorage est bloqué, le site doit quand même fonctionner.
  try {
    // On lit le thème choisi par l'utilisateur lors d'une visite précédente.
    var saved = localStorage.getItem("sv-theme");

    // Si aucun thème n'a été choisi, on suit la préférence du système d'exploitation.
    var prefersLight = window.matchMedia("(prefers-color-scheme: light)").matches;

    // On décide du thème final.
    var theme = saved === "light" || saved === "dark" ? saved : prefersLight ? "light" : "dark";

    // On l'écrit sur la balise <html> pour que le CSS puisse réagir.
    document.documentElement.setAttribute("data-theme", theme);

    // On affiche le préloader uniquement à la première page de la session.
    var introSeen = sessionStorage.getItem("atlas-intro-seen");
    var reduceMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;

    if (!introSeen && !reduceMotion) {
      document.documentElement.classList.add("is-intro");
    }

    // Les révélations au scroll sont activées uniquement si JavaScript tourne.
    document.documentElement.classList.add("motion-ready");
  } catch (error) {
    // En cas de problème, on applique simplement le thème sombre par défaut.
    document.documentElement.setAttribute("data-theme", "dark");
  }
})();
