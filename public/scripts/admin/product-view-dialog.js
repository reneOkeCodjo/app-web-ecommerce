/**
 * Product details dialog (view + delete + open edit).
 */

import {
  formatMoney,
  hasPromotion,
  priceAvailable,
  priceTtc,
} from "./product-pricing.js";

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
    const promotion = product.promotion;
    const ttc = product.prixTtc ?? priceTtc(product.prixHt);
    const available =
      product.prixDisponible ??
      priceAvailable(product.prixHt, promotion);

    dialog.querySelectorAll("[data-view-field]").forEach((node) => {
      const key = node.getAttribute("data-view-field");
      let value = product[key];

      if (key === "prixHt" && typeof value === "number") {
        value = formatMoney(value);
      }

      if (key === "promotionLabel") {
        value =
          promotion == null || promotion <= 0
            ? "Aucune"
            : `${formatMoney(promotion)} %`;
      }

      node.textContent = value == null ? "" : String(value);
    });

    const ttcNode = dialog.querySelector("[data-view-prix-ttc]");
    const dispoNode = dialog.querySelector("[data-view-prix-dispo]");

    if (ttcNode) {
      if (hasPromotion(promotion)) {
        ttcNode.innerHTML = `<s class="admin-price__was">${formatMoney(ttc)}</s>`;
      } else {
        ttcNode.textContent = formatMoney(ttc);
      }
    }

    if (dispoNode) {
      dispoNode.textContent = formatMoney(available);
    }

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
      if (!window.confirm("Supprimer ce produit ?")) {
        event.preventDefault();
      }
    });
  }

  return { openView };
}
