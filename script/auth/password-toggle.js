/**
 * Bouton œil : bascule les champs mot de passe entre `type="password"` et `type="text"`.
 * Cible tout `.form-field` contenant un input dont le `name` se termine par `"password"`.
 */

/**
 * Bascule un champ mot de passe entre masqué et texte en clair.
 * @param {HTMLElement} field - Le `.form-field` qui enveloppe l'input et le bouton
 * @returns {void}
 */
function togglePasswordVisibility(field) {
    const passwordToggle = field.querySelector('.password-toggle');
    if (!passwordToggle) return;

    const input = field.querySelector('input');
    input.type = input.type === 'password' ? 'text' : 'password';
    // `.closed` pilote l'icône œil ouvert/fermé dans le CSS
    passwordToggle.classList.toggle('closed');
}

/**
 * Relie les gestionnaires de clic sur chaque bouton œil du formulaire donné.
 * @param {HTMLFormElement} form
 * @returns {void}
 */
function initPasswordToggles(form) {
    const passwordFields = form.querySelectorAll('div.form-field:has(input[name$="password"])');
    passwordFields.forEach((field) => {
        const passwordToggle = field.querySelector('.password-toggle');
        if (!passwordToggle) return;
        passwordToggle.addEventListener('click', () => togglePasswordVisibility(field));
    });
}

const form = document.querySelector('form');
if (form) {
    initPasswordToggles(form);
}
