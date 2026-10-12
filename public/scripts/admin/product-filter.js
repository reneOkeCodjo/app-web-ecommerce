/**
 * Category filter for the products table.
 */

/**
 * @param {HTMLSelectElement | null} select
 * @param {string} rowSelector
 */
export function initCategoryFilter(select, rowSelector = ".admin-table__row") {
  if (!select) {
    return;
  }

  const apply = () => {
    const selected = select.value;
    document.querySelectorAll(rowSelector).forEach((row) => {
      const type = row.getAttribute("data-product-type") || "";
      row.hidden = selected !== "" && type !== selected;
    });
  };

  select.addEventListener("change", apply);
  apply();
}
