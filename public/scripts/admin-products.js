/**
 * Admin products page bootstrap: wires catalog, filter, view and form dialogs.
 */
import { indexProductsById, readProducts } from "./admin/product-catalog.js";
import { bindDialogChrome } from "./admin/dialogs.js";
import { initCategoryFilter } from "./admin/product-filter.js";
import { initProductFormDialog } from "./admin/product-form-dialog.js";
import { initTableSort } from "./admin/product-sort.js";
import { initTablePagination } from "./admin/product-pagination.js";
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

  const adminTable = document.querySelector(".admin-table");
  const paginationNav = document.getElementById("admin-pagination");
  const pageSizeSelect = document.getElementById("admin-page-size");

  if (
    adminTable instanceof HTMLTableElement &&
    paginationNav instanceof HTMLElement
  ) {
    const initialPageSize =
      pageSizeSelect instanceof HTMLSelectElement
        ? parseInt(pageSizeSelect.value, 10) || 10
        : 10;

    const pagination = initTablePagination({
      table: adminTable,
      container: paginationNav,
      pageSize: initialPageSize,
    });

    // Filter marks rows then resets to page 1; sort reorders then keeps page in range.
    initCategoryFilter(
      categoryFilter instanceof HTMLSelectElement ? categoryFilter : null,
      { onChange: pagination.reset }
    );
    initTableSort(adminTable, products, { onChange: pagination.refresh });

    if (pageSizeSelect instanceof HTMLSelectElement) {
      pageSizeSelect.addEventListener("change", () => {
        pagination.setPageSize(parseInt(pageSizeSelect.value, 10) || 10);
      });
    }

    // Guarantee an initial paginated render even if filter/sort bailed early.
    pagination.refresh();
  } else {
    // Fallback: no pagination container — preserve original filter/sort behaviour.
    initCategoryFilter(
      categoryFilter instanceof HTMLSelectElement ? categoryFilter : null
    );
    if (adminTable instanceof HTMLTableElement) {
      initTableSort(adminTable, products);
    }
  }

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
