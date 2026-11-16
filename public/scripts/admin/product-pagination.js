/**
 * Client-side pagination for the admin products table.
 *
 * The table rows are server-rendered. Visibility is the single responsibility
 * of this module: a row is shown only when it passes the category filter
 * (i.e. does NOT carry the `admin-table__row--filtered` class) AND belongs to
 * the current page slice. Filter and sort modules call back into `refresh()`
 * after they mutate the rows.
 */

const ROW_SELECTOR = ".admin-table__row";
const FILTERED_CLASS = "admin-table__row--filtered";

/**
 * @param {number} current
 * @param {number} total
 * @returns {(number|'...')[]}
 */
function getPageNumbers(current, total) {
  if (total <= 7) return Array.from({ length: total }, (_, i) => i + 1);
  if (current <= 4) return [1, 2, 3, 4, 5, "...", total];
  if (current >= total - 3) return [1, "...", total - 4, total - 3, total - 2, total - 1, total];
  return [1, "...", current - 1, current, current + 1, "...", total];
}

/**
 * @param {{
 *   table: HTMLTableElement,
 *   container: HTMLElement,
 *   pageSize?: number
 * }} options
 * @returns {{ refresh: () => void, reset: () => void, setPageSize: (size: number) => void }}
 */
export function initTablePagination({ table, container, pageSize = 10 }) {
  let currentPage = 1;
  let size = pageSize;

  /** @returns {HTMLTableRowElement[]} Rows that pass the filter, in current DOM (sorted) order. */
  const eligibleRows = () => {
    const tbody = table.querySelector("tbody");
    if (!tbody) return [];
    return /** @type {HTMLTableRowElement[]} */ (
      Array.from(tbody.querySelectorAll(ROW_SELECTOR))
    ).filter((row) => !row.classList.contains(FILTERED_CLASS));
  };

  /**
   * @param {number} total
   * @param {number} pageCount
   */
  const renderControls = (total, pageCount) => {
    container.innerHTML = "";

    const info = document.createElement("span");
    info.className = "pagination__info";
    info.textContent = `${total} produit${total !== 1 ? "s" : ""}`;
    container.appendChild(info);

    if (pageCount <= 1) return;

    const controls = document.createElement("div");
    controls.className = "pagination__controls";

    /**
     * @param {string} label
     * @param {number} target
     * @param {boolean} disabled
     * @param {boolean} active
     */
    const makeBtn = (label, target, disabled, active) => {
      const btn = document.createElement("button");
      btn.type = "button";
      btn.className = "pagination__btn" + (active ? " pagination__btn--active" : "");
      btn.textContent = label;
      btn.disabled = disabled;
      btn.setAttribute("aria-current", active ? "page" : "false");
      if (!disabled && !active) {
        btn.addEventListener("click", () => {
          currentPage = target;
          apply();
          table.scrollIntoView({ behavior: "smooth", block: "start" });
        });
      }
      return btn;
    };

    controls.appendChild(makeBtn("‹", currentPage - 1, currentPage === 1, false));

    for (const p of getPageNumbers(currentPage, pageCount)) {
      if (p === "...") {
        const dots = document.createElement("span");
        dots.className = "pagination__dots";
        dots.textContent = "…";
        controls.appendChild(dots);
      } else {
        controls.appendChild(makeBtn(String(p), p, false, p === currentPage));
      }
    }

    controls.appendChild(makeBtn("›", currentPage + 1, currentPage === pageCount, false));

    container.appendChild(controls);
  };

  /** Recompute visibility for the current page and re-render controls. */
  const apply = () => {
    const rows = eligibleRows();
    const total = rows.length;
    const pageCount = Math.max(1, Math.ceil(total / size));

    if (currentPage > pageCount) currentPage = pageCount;
    if (currentPage < 1) currentPage = 1;

    const start = (currentPage - 1) * size;
    const pageSet = new Set(rows.slice(start, start + size));

    // Pagination owns `hidden`: filtered-out rows are never in pageSet → stay hidden.
    const allRows = /** @type {HTMLTableRowElement[]} */ (
      Array.from(table.querySelectorAll(ROW_SELECTOR))
    );
    for (const row of allRows) {
      row.hidden = !pageSet.has(row);
    }

    renderControls(total, pageCount);
  };

  return {
    refresh: apply,
    reset: () => {
      currentPage = 1;
      apply();
    },
    setPageSize: (next) => {
      size = next > 0 ? next : size;
      currentPage = 1;
      apply();
    },
  };
}
