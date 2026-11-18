/*
  admin-users.js
  --------------
  Confirmation légère avant suppression d'un compte.
*/

document.querySelectorAll("form[data-confirm]").forEach((form) => {
  form.addEventListener("submit", (event) => {
    const message = form.getAttribute("data-confirm") || "Confirmer cette action ?";

    if (!window.confirm(message)) {
      event.preventDefault();
    }
  });
});
