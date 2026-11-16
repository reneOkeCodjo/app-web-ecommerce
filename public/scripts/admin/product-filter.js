/**
 * Category filter for the products table.
 *
 * The filter does not hide rows directly: it only marks non-matching rows with
 * the `admin-table__row--filtered` class, then notifies the caller (pagination)
 * via `onChange`. Pagination is the single authority over `row.hidden`.
 */

/**
 * @param {HTMLSelectElement | null} select
 * @param {{ onChange?: () => void, rowSelector?: string }} [options]
 */
export function initCategoryFilter(select, options = {}) {
  if (!select) {
    return;
  }

  const { onChange, rowSelector = ".admin-table__row" } = options;

  const apply = () => {
    const selected = select.value;
    document.querySelectorAll(rowSelector).forEach((row) => {
      const type = row.getAttribute("data-product-type") || "";
      const filteredOut = selected !== "" && type !== selected;
      row.classList.toggle("admin-table__row--filtered", filteredOut);
    });
    if (typeof onChange === "function") {
      onChange();
    }
  };

  select.addEventListener("change", apply);
  apply();
}
