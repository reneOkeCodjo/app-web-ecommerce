/**
 * Product details dialog (view + delete + open edit).
 */

/**
 * @param {{
 *   dialog: HTMLDialogElement,
 *   deleteForm: HTMLFormElement | null,
 *   byId: Map<string, import('./product-catalog.js').AdminProduct>,
 *   onEdit: (product: import('./product-catalog.js').AdminProduct) => void
 * }} options
 */
export function initProductViewDialog({ dialog, deleteForm, byId, onEdit }) {
  const fillView = (product) => {
    dialog.querySelectorAll("[data-view-field]").forEach((node) => {
      const key = node.getAttribute("data-view-field");
      let value = product[key];
      if (key === "prixHt" && typeof value === "number") {
        value = value.toFixed(2);
      }
      node.textContent = value == null ? "" : String(value);
    });

    if (deleteForm) {
      const idInput = deleteForm.querySelector('input[name="id_p"]');
      if (idInput) {
        idInput.value = String(product.id);
      }
    }
  };

  const openView = (product) => {
    fillView(product);
    dialog.showModal();
  };

  const editFromView = document.querySelector("[data-open-edit-from-view]");
  if (editFromView) {
    editFromView.addEventListener("click", () => {
      const id = deleteForm?.querySelector('input[name="id_p"]')?.value;
      const product = id ? byId.get(id) : null;
      if (!product) {
        return;
      }
      dialog.close();
      onEdit(product);
    });
  }

  if (deleteForm) {
    deleteForm.addEventListener("submit", (event) => {
      if (!window.confirm("Supprimer ce produit du catalogue mock ?")) {
        event.preventDefault();
      }
    });
  }

  return { openView };
}
