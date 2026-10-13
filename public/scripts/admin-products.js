/**
 * Admin products page bootstrap: wires catalog, filter, view and form dialogs.
 */
import { indexProductsById, readProducts } from "./admin/product-catalog.js";
import { bindDialogChrome } from "./admin/dialogs.js";
import { initCategoryFilter } from "./admin/product-filter.js";
import { initProductFormDialog } from "./admin/product-form-dialog.js";
import { bindProductTableTriggers } from "./admin/product-table.js";
import { initProductViewDialog } from "./admin/product-view-dialog.js";

const products = readProducts(window.__ADMIN_PRODUCTS__);
const byId = indexProductsById(products);

const viewDialog = document.getElementById("product-view-dialog");
const formDialog = document.getElementById("product-form-dialog");
const form = document.getElementById("product-form");
const deleteForm = document.querySelector("[data-delete-form]");
const categoryFilter = document.getElementById("product-category-filter");

if (
  viewDialog instanceof HTMLDialogElement &&
  formDialog instanceof HTMLDialogElement &&
  form instanceof HTMLFormElement
) {
  const { openCreateForm, openEditForm, openRestoredForm } =
    initProductFormDialog({
      dialog: formDialog,
      form,
    });

  const { openView } = initProductViewDialog({
    dialog: viewDialog,
    deleteForm: deleteForm instanceof HTMLFormElement ? deleteForm : null,
    byId,
    onEdit: openEditForm,
  });

  bindDialogChrome([viewDialog, formDialog]);
  initCategoryFilter(
    categoryFilter instanceof HTMLSelectElement ? categoryFilter : null
  );

  bindProductTableTriggers({
    onCreate: openCreateForm,
    onView: (productId) => {
      const product = byId.get(productId);
      if (product) {
        openView(product);
      }
    },
  });

  const formState = window.__ADMIN_FORM_STATE__;
  if (
    formState &&
    typeof formState === "object" &&
    formState.reopen === true &&
    typeof openRestoredForm === "function"
  ) {
    openRestoredForm();
  }
}
