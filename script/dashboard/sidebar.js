/**
 * Barre latérale du dashboard : ouverture / fermeture des libellés + overlay sombre.
 * Un clic sur l'overlay ferme la barre.
 */
const toggleBtn = document.querySelector('#toggle-btn');
const sidebar = document.querySelector('#dashboard-sidebar');
const sidebarOverlay = document.querySelector('#sidebar-overlay');
const sidebarTexts = document.querySelectorAll('.sidebar-text');

if (toggleBtn && sidebar && sidebarOverlay) {
    /**
     * Développe ou réduit la barre latérale et synchronise l'overlay / la visibilité des libellés.
     * @param {boolean} isOpen - `true` pour ouvrir, `false` pour fermer
     * @returns {void}
     */
    function setSidebarOpen(isOpen) {
        sidebarTexts.forEach((text) => {
            text.classList.toggle('closed', !isOpen);
        });
        sidebar.classList.toggle('expanded', isOpen);
        sidebarOverlay.classList.toggle('open', isOpen);
        sidebarOverlay.setAttribute('aria-hidden', String(!isOpen));
    }

    /**
     * Bascule l'état ouvert/fermé au clic sur le bouton burger.
     * @returns {void}
     */
    function handleToggleClick() {
        const isOpen = !sidebar.classList.contains('expanded');
        setSidebarOpen(isOpen);
    }

    /**
     * Ferme la barre latérale au clic sur l'overlay sombre.
     * @returns {void}
     */
    function handleOverlayClick() {
        setSidebarOpen(false);
    }

    toggleBtn.addEventListener('click', handleToggleClick);
    sidebarOverlay.addEventListener('click', handleOverlayClick);
}
