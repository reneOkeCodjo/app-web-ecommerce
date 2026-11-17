/*
  form-validation.js
  ----------------
  Affiche un message personnalisé quand un champ ne respecte pas
  son motif HTML (email, mot de passe, etc.).
  Ce fichier remplace les anciens attributs onclick/oninvalid du HTML.
*/

(function () {
  // On attend que la page soit prête.
  document.addEventListener("DOMContentLoaded", function () {
    // On cherche tous les champs qui possèdent un message d'erreur personnalisé.
    var fields = document.querySelectorAll("input[data-error-message]");

    // On parcourt chaque champ.
    fields.forEach(function (field) {
      // Quand le navigateur détecte que le champ est invalide...
      field.addEventListener("invalid", function () {
        // ...si le problème vient du motif (format attendu)...
        if (field.validity.patternMismatch) {
          // ...on affiche le message écrit dans data-error-message.
          field.setCustomValidity(field.getAttribute("data-error-message") || "Valeur invalide.");
        }
      });

      // Dès que l'utilisateur retape quelque chose...
      field.addEventListener("input", function () {
        // ...on efface le message pour ne pas le bloquer.
        field.setCustomValidity("");
      });
    });
  });
})();
