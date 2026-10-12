/**
 * Product create / update form dialog.
 */

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
  const formDate = document.getElementById("product-form-date");
  const formStock = document.getElementById("product-form-stock");
  const formSubmit = document.getElementById("product-form-submit");

  if (
    !formTitle ||
    !formAction ||
    !formId ||
    !formType ||
    !formDesignation ||
    !formPrix ||
    !formDate ||
    !formStock ||
    !formSubmit
  ) {
    return {
      openCreateForm: () => {},
      openEditForm: () => {},
    };
  }

  const openCreateForm = () => {
    form.reset();
    formAction.value = "create";
    formId.value = "";
    formTitle.textContent = "Ajouter un produit";
    formSubmit.textContent = "Créer";
    formDate.value = new Date().toISOString().slice(0, 10);
    formStock.value = "0";
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
    formDate.value = product.dateIn;
    formStock.value = String(product.stock);
    formTitle.textContent = "Modifier le produit";
    formSubmit.textContent = "Enregistrer";
    dialog.showModal();
  };

  return { openCreateForm, openEditForm };
}
