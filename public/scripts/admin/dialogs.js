/**
 * Shared dialog open/close behavior.
 */

/**
 * Close buttons and backdrop click for one or more dialogs.
 * @param {Iterable<HTMLDialogElement | null>} dialogs
 */
export function bindDialogChrome(dialogs) {
  const list = [...dialogs].filter(Boolean);

  document.querySelectorAll("[data-close-dialog]").forEach((button) => {
    button.addEventListener("click", () => {
      const dialog = button.closest("dialog");
      if (dialog) {
        dialog.close();
      }
    });
  });

  list.forEach((dialog) => {
    dialog.addEventListener("click", (event) => {
      if (event.target === dialog) {
        dialog.close();
      }
    });
  });
}
