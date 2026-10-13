/**
 * Product create / update form dialog.
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
 *   form: HTMLFormElement
 * }} options
 */
export function initProductFormDialog({ dialog, form }) {
  const formTitle = document.getElementById("product-form-title");
  const formAction = document.getElementById("product-form-action");
  const formId = document.getElementById("product-form-id");
  const formType = document.getElementById("product-form-type");
  const formDesignation = document.getElementById("product-form-designation");
  const formPrix = document.getElementById("product-form-prix");
  const formPromo = document.getElementById("product-form-promo");
  const formDate = document.getElementById("product-form-date");
  const formStock = document.getElementById("product-form-stock");
  const formSubmit = document.getElementById("product-form-submit");
  const previewTtc = document.querySelector("[data-preview-ttc]");
  const previewDispo = document.querySelector("[data-preview-dispo]");

  if (
    !formTitle ||
    !formAction ||
    !formId ||
    !formType ||
    !formDesignation ||
    !formPrix ||
    !formPromo ||
    !formDate ||
    !formStock ||
    !formSubmit ||
    !previewTtc ||
    !previewDispo
  ) {
    return {
      openCreateForm: () => {},
      openEditForm: () => {},
    };
  }

  const refreshPricePreview = () => {
    const priceHt = Number.parseFloat(formPrix.value);
    const promoRaw = formPromo.value.trim();
    const promotion =
      promoRaw === "" ? null : Number.parseFloat(promoRaw);

    if (!Number.isFinite(priceHt) || priceHt < 0) {
      previewTtc.textContent = "—";
      previewTtc.classList.remove("admin-price__was");
      previewDispo.textContent = "—";
      return;
    }

    const ttc = priceTtc(priceHt);
    const available = priceAvailable(
      priceHt,
      Number.isFinite(promotion) ? promotion : null
    );

    if (hasPromotion(Number.isFinite(promotion) ? promotion : null)) {
      previewTtc.innerHTML = `<s class="admin-price__was">${formatMoney(ttc)}</s>`;
      previewDispo.textContent = formatMoney(available);
    } else {
      previewTtc.textContent = formatMoney(ttc);
      previewDispo.textContent = formatMoney(available);
    }
  };

  formPrix.addEventListener("input", refreshPricePreview);
  formPromo.addEventListener("input", refreshPricePreview);

  const openCreateForm = () => {
    form.reset();
    formAction.value = "create";
    formId.value = "";
    formTitle.textContent = "Ajouter un produit";
    formSubmit.textContent = "Créer";
    formDate.value = new Date().toISOString().slice(0, 10);
    formStock.value = "0";
    formPromo.value = "";
    refreshPricePreview();
    dialog.showModal();
  };

  /**
   * @param {import('./product-catalog.js').AdminProduct} product
   */
  const openEditForm = (product) => {
    formAction.value = "update";
    formId.value = String(product.id);
    formType.value = product.type;
    formDesignation.value = product.designation;
    formPrix.value = String(product.prixHt);
    formPromo.value =
      product.promotion == null ? "" : String(product.promotion);
    formDate.value = product.dateIn;
    formStock.value = String(product.stock);
    formTitle.textContent = "Modifier le produit";
    formSubmit.textContent = "Enregistrer";
    refreshPricePreview();
    dialog.showModal();
  };

  return { openCreateForm, openEditForm };
}
