(() => {
  const products = Array.isArray(window.__ADMIN_PRODUCTS__)
    ? window.__ADMIN_PRODUCTS__
    : [];

  const byId = new Map(
    products.map((product) => [String(product.id), product])
  );

  const viewDialog = document.getElementById("product-view-dialog");
  const formDialog = document.getElementById("product-form-dialog");
  const form = document.getElementById("product-form");
  const formTitle = document.getElementById("product-form-title");
  const formAction = document.getElementById("product-form-action");
  const formId = document.getElementById("product-form-id");
  const formType = document.getElementById("product-form-type");
  const formDesignation = document.getElementById("product-form-designation");
  const formPrix = document.getElementById("product-form-prix");
  const formDate = document.getElementById("product-form-date");
  const formStock = document.getElementById("product-form-stock");
  const formSubmit = document.getElementById("product-form-submit");
  const deleteForm = document.querySelector("[data-delete-form]");

  if (!viewDialog || !formDialog || !form) {
    return;
  }

  const fillView = (product) => {
    viewDialog.querySelectorAll("[data-view-field]").forEach((node) => {
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

  const openCreateForm = () => {
    form.reset();
    formAction.value = "create";
    formId.value = "";
    formTitle.textContent = "Add product";
    formSubmit.textContent = "Create";
    formDate.value = new Date().toISOString().slice(0, 10);
    formStock.value = "0";
    formDialog.showModal();
  };

  const openEditForm = (product) => {
    formAction.value = "update";
    formId.value = String(product.id);
    formType.value = product.type;
    formDesignation.value = product.designation;
    formPrix.value = String(product.prixHt);
    formDate.value = product.dateIn;
    formStock.value = String(product.stock);
    formTitle.textContent = "Update product";
    formSubmit.textContent = "Save";
    formDialog.showModal();
  };

  const openView = (product) => {
    fillView(product);
    viewDialog.showModal();
  };

  document.querySelectorAll("[data-open-dialog]").forEach((trigger) => {
    trigger.addEventListener("click", () => {
      const dialogId = trigger.getAttribute("data-open-dialog");
      const mode = trigger.getAttribute("data-form-mode");
      const productId = trigger.getAttribute("data-product-id");

      if (dialogId === "product-form-dialog" && mode === "create") {
        openCreateForm();
        return;
      }

      if (dialogId === "product-view-dialog" && productId) {
        const product = byId.get(productId);
        if (product) {
          openView(product);
        }
      }
    });

    trigger.addEventListener("keydown", (event) => {
      if (event.key !== "Enter" && event.key !== " ") {
        return;
      }
      if (!trigger.hasAttribute("data-product-id")) {
        return;
      }
      event.preventDefault();
      trigger.click();
    });
  });

  document.querySelectorAll("[data-close-dialog]").forEach((button) => {
    button.addEventListener("click", () => {
      const dialog = button.closest("dialog");
      if (dialog) {
        dialog.close();
      }
    });
  });

  const editFromView = document.querySelector("[data-open-edit-from-view]");
  if (editFromView) {
    editFromView.addEventListener("click", () => {
      const id = deleteForm?.querySelector('input[name="id_p"]')?.value;
      const product = id ? byId.get(id) : null;
      if (!product) {
        return;
      }
      viewDialog.close();
      openEditForm(product);
    });
  }

  if (deleteForm) {
    deleteForm.addEventListener("submit", (event) => {
      if (!window.confirm("Remove this product from the mock catalog?")) {
        event.preventDefault();
      }
    });
  }

  [viewDialog, formDialog].forEach((dialog) => {
    dialog.addEventListener("click", (event) => {
      if (event.target === dialog) {
        dialog.close();
      }
    });
  });
})();
