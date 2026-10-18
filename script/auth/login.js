/**
 * Contrôle côté client avant le POST de connexion.
 * Vérifie seulement que le nom d'utilisateur et le mot de passe ne sont pas vides ;
 * l'authentification réelle est gérée en PHP (`index.php`).
 */

/**
 * Affiche une erreur côté client sur `#feedback-message` et masque le feedback serveur.
 * @param {string} message
 * @returns {void}
 */
function showLoginClientError(message) {
    const feedbackMessage = document.querySelector('#feedback-message');
    const feedbackServer = document.querySelector('#feedback-server');
    if (!feedbackMessage) return;

    feedbackMessage.innerHTML = message;
    feedbackMessage.classList.add('show');
    feedbackMessage.style.color = 'red';
    feedbackServer?.classList.remove('show');
}

/**
 * Gère la soumission du formulaire de connexion :
 * bloque les identifiants vides, sinon autorise le POST.
 * @param {SubmitEvent} event
 * @returns {void}
 */
function handleLoginSubmit(event) {
    event.preventDefault();

    const loginForm = event.currentTarget;
    const username = document.querySelector('#login-username')?.value.trim() ?? '';
    const password = document.querySelector('#login-password')?.value.trim() ?? '';

    if (!username || !password) {
        showLoginClientError('Username and password are required');
        return;
    }

    loginForm.submit();
}

const loginForm = document.querySelector('#login-form');
if (loginForm) {
    loginForm.addEventListener('submit', handleLoginSubmit);
}
