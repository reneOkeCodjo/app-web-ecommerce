/**
 * Affiche le feedback PHP après le chargement de la page.
 * Le message provient de `data-message` sur `#feedback-server` (défini dans index.php).
 */

/**
 * Lit le message serveur dans le DOM et révèle `#feedback-server` s'il est présent.
 * @returns {void}
 */
function showServerFeedback() {
    const feedbackServer = document.querySelector('#feedback-server');
    if (!feedbackServer) return;

    const serverMessage = feedbackServer.dataset.message || '';
    if (serverMessage) {
        feedbackServer.innerHTML = serverMessage;
        feedbackServer.classList.add('show');
    }
}

window.addEventListener('load', showServerFeedback);
