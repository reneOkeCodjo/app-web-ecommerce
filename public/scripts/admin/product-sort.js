/**
 * Sortable column headers for the admin products table.
 *
 * @typedef {import('./product-catalog.js').AdminProduct} AdminProduct
 */

/**
 * @param {HTMLTableElement} table
 * @param {AdminProduct[]} products
 * @param {{ onChange?: () => void }} [options]
 */
export function initTableSort(table, products, options = {}) {
  const { onChange } = options;
  const headers = /** @type {NodeListOf<HTMLTableCellElement>} */ (
    table.querySelectorAll("th[data-sort-key]")
  );

  if (headers.length === 0) {
    return;
  }

  /** @type {string} */
  let currentKey = "id";
  /** @type {"asc" | "desc"} */
  let currentDir = "desc";

  /**
   * Compare two products by the given key.
   *
   * @param {AdminProduct} a
   * @param {AdminProduct} b
   * @param {string} key
   * @returns {number}
   */
  const compare = (a, b, key) => {
    const valA = a[key];
    const valB = b[key];

    if (typeof valA === "string" && typeof valB === "string") {
      return valA.localeCompare(valB, "fr", { sensitivity: "base" });
    }

    return Number(valA) - Number(valB);
  };

  /**
   * Sort the visible table rows in-place according to the current key/dir.
   */
  const applySort = () => {
    const tbody = table.querySelector("tbody");
    if (!tbody) return;

    const rows = /** @type {HTMLTableRowElement[]} */ (
      Array.from(tbody.querySelectorAll(".admin-table__row"))
    );

    // Build a map from product id to sorted index.
    const sorted = [...products].sort((a, b) => {
      const cmp = compare(a, b, currentKey);
      return currentDir === "asc" ? cmp : -cmp;
    });

    const order = new Map(sorted.map((p, i) => [String(p.id), i]));

    rows.sort((rowA, rowB) => {
      const idA = rowA.getAttribute("data-product-id") || "";
      const idB = rowB.getAttribute("data-product-id") || "";
      return (order.get(idA) ?? 0) - (order.get(idB) ?? 0);
    });

    // Re-append rows in sorted order (preserves hidden state from filter).
    for (const row of rows) {
      tbody.appendChild(row);
    }

    // Update header indicators.
    headers.forEach((th) => {
      const key = th.getAttribute("data-sort-key");
      const isActive = key === currentKey;

      th.classList.toggle("admin-th--sorted", isActive);
      th.setAttribute("aria-sort", isActive ? currentDir === "asc" ? "ascending" : "descending" : "none");

      // Update indicator text.
      let indicator = th.querySelector(".admin-sort-indicator");
      if (!indicator) {
        indicator = document.createElement("span");
        indicator.className = "admin-sort-indicator";
        indicator.setAttribute("aria-hidden", "true");
        th.appendChild(indicator);
      }
      indicator.textContent = isActive ? (currentDir === "asc" ? " ▲" : " ▼") : "";
    });

    if (typeof onChange === "function") {
      onChange();
    }
  };

  // Bind click handlers.
  headers.forEach((th) => {
    th.classList.add("admin-th--sortable");

    th.addEventListener("click", () => {
      const key = th.getAttribute("data-sort-key");
      if (!key) return;

      if (key === currentKey) {
        currentDir = currentDir === "asc" ? "desc" : "asc";
      } else {
        currentKey = key;
        currentDir = "asc";
      }

      applySort();
    });
  });

  // Apply default sort (id descending).
  applySort();
}
