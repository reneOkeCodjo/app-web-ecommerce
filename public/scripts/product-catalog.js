/**
 * Client-side filtering, sorting, search and pagination for the public product catalog.
 *
 * Expects:
 *   window.__CATALOG_PRODUCTS__  — array of product objects injected by PHP
 */

/** @typedef {{ id: number, type: string, designation: string, prixHt: number, promotion: number|null, prixTtc: number, prixDisponible: number, dateIn: string, timesIn: string, stock: number, image: string|null }} CatalogProduct */

(function () {
  "use strict";

  const products = /** @type {CatalogProduct[]} */ (
    Array.isArray(window.__CATALOG_PRODUCTS__) ? window.__CATALOG_PRODUCTS__ : []
  );

  const grid           = document.getElementById("catalog-grid");
  const paginationNav  = document.getElementById("catalog-pagination");
  const categoryFilter = document.getElementById("catalog-category-filter");
  const sortSelect     = document.getElementById("catalog-sort");
  const searchInput    = document.getElementById("catalog-search");
  const pageSizeSelect = document.getElementById("catalog-page-size");

  if (!grid) return;

  /** @type {string} */
  let currentCategory = "";
  /** @type {string} */
  let currentSort = "recent";
  /** @type {string} */
  let currentSearch = "";
  /** @type {number} */
  let currentPage = 1;
  /** @type {number} */
  let currentPageSize = 12;

  /**
   * @param {number} value
   * @returns {string}
   */
  const formatPrice = (value) => value.toFixed(2) + " €";

  /**
   * @param {CatalogProduct} product
   * @returns {HTMLElement}
   */
  const buildCard = (product) => {
    const hasPromo = product.promotion !== null && product.promotion > 0;
    const inStock  = product.stock > 0;

    const card = document.createElement("article");
    card.className = "catalog-card";
    card.setAttribute("data-product-type", product.type);

    const imageWrap = document.createElement("div");
    imageWrap.className = "catalog-card__image-wrap";
    if (product.image) {
      const img = document.createElement("img");
      img.className   = "catalog-card__image";
      img.src         = product.image;
      img.alt         = product.designation;
      img.loading     = "lazy";
      imageWrap.appendChild(img);
    } else {
      const placeholder = document.createElement("span");
      placeholder.className = "catalog-card__placeholder";
      placeholder.setAttribute("aria-hidden", "true");
      placeholder.textContent = "📷";
      imageWrap.appendChild(placeholder);
    }
    card.appendChild(imageWrap);

    const body = document.createElement("div");
    body.className = "catalog-card__body";

    const categoryBadge = document.createElement("span");
    categoryBadge.className  = "catalog-card__category";
    categoryBadge.textContent = product.type;
    body.appendChild(categoryBadge);

    const name = document.createElement("h2");
    name.className   = "catalog-card__name";
    name.textContent = product.designation;
    body.appendChild(name);

    const pricing = document.createElement("div");
    pricing.className = "catalog-card__pricing";

    if (hasPromo) {
      const was = document.createElement("span");
      was.className   = "catalog-card__price-was";
      was.textContent = formatPrice(product.prixTtc);
      pricing.appendChild(was);

      const now = document.createElement("span");
      now.className   = "catalog-card__price catalog-card__price--promo";
      now.textContent = formatPrice(product.prixDisponible);
      pricing.appendChild(now);
    } else {
      const price = document.createElement("span");
      price.className   = "catalog-card__price";
      price.textContent = formatPrice(product.prixDisponible);
      pricing.appendChild(price);
    }

    body.appendChild(pricing);

    const stockBadge = document.createElement("span");
    stockBadge.className =
      "catalog-card__stock " +
      (inStock ? "catalog-card__stock--in" : "catalog-card__stock--out");
    stockBadge.textContent = inStock ? "En stock" : "Rupture de stock";
    body.appendChild(stockBadge);

    card.appendChild(body);
    return card;
  };

  /**
   * @param {CatalogProduct[]} list
   * @param {string} sortKey
   * @returns {CatalogProduct[]}
   */
  const sortProducts = (list, sortKey) => {
    const sorted = [...list];
    switch (sortKey) {
      case "price-asc":
        sorted.sort((a, b) => a.prixDisponible - b.prixDisponible);
        break;
      case "price-desc":
        sorted.sort((a, b) => b.prixDisponible - a.prixDisponible);
        break;
      case "name-asc":
        sorted.sort((a, b) =>
          a.designation.localeCompare(b.designation, "fr", { sensitivity: "base" })
        );
        break;
      case "name-desc":
        sorted.sort((a, b) =>
          b.designation.localeCompare(a.designation, "fr", { sensitivity: "base" })
        );
        break;
      case "stock":
        sorted.sort((a, b) => b.stock - a.stock);
        break;
      case "recent":
      default:
        sorted.sort((a, b) => b.id - a.id);
        break;
    }
    return sorted;
  };

  /**
   * Returns the page numbers to display, inserting '...' for gaps.
   * @param {number} current
   * @param {number} total
   * @returns {(number|'...')[]}
   */
  const getPageNumbers = (current, total) => {
    if (total <= 7) return Array.from({ length: total }, (_, i) => i + 1);
    if (current <= 4) return [1, 2, 3, 4, 5, "...", total];
    if (current >= total - 3) return [1, "...", total - 4, total - 3, total - 2, total - 1, total];
    return [1, "...", current - 1, current, current + 1, "...", total];
  };

  /**
   * @param {number} totalItems
   * @param {number} pageCount
   */
  const renderPagination = (totalItems, pageCount) => {
    if (!paginationNav) return;
    paginationNav.innerHTML = "";

    const info = document.createElement("span");
    info.className   = "pagination__info";
    info.textContent = `${totalItems} résultat${totalItems !== 1 ? "s" : ""}`;
    paginationNav.appendChild(info);

    if (pageCount <= 1) return;

    const controls = document.createElement("div");
    controls.className = "pagination__controls";

    /** @param {string} label @param {number} targetPage @param {boolean} disabled @param {boolean} active */
    const makeBtn = (label, targetPage, disabled, active) => {
      const btn = document.createElement("button");
      btn.className   = "pagination__btn" + (active ? " pagination__btn--active" : "");
      btn.textContent = label;
      btn.disabled    = disabled;
      btn.setAttribute("aria-current", active ? "page" : "false");
      if (!disabled) {
        btn.addEventListener("click", () => {
          currentPage = targetPage;
          render();
          grid.scrollIntoView({ behavior: "smooth", block: "start" });
        });
      }
      return btn;
    };

    controls.appendChild(makeBtn("‹", currentPage - 1, currentPage === 1, false));

    for (const p of getPageNumbers(currentPage, pageCount)) {
      if (p === "...") {
        const dots = document.createElement("span");
        dots.className   = "pagination__dots";
        dots.textContent = "…";
        controls.appendChild(dots);
      } else {
        controls.appendChild(makeBtn(String(p), p, p === currentPage, p === currentPage));
      }
    }

    controls.appendChild(makeBtn("›", currentPage + 1, currentPage === pageCount, false));

    paginationNav.appendChild(controls);
  };

  /**
   * Re-render the grid and pagination based on current state.
   */
  const render = () => {
    let filtered = products;

    if (currentCategory !== "") {
      filtered = filtered.filter((p) => p.type === currentCategory);
    }

    if (currentSearch !== "") {
      const query = currentSearch.toLowerCase();
      filtered = filtered.filter((p) =>
        p.designation.toLowerCase().includes(query)
      );
    }

    const sorted    = sortProducts(filtered, currentSort);
    const total     = sorted.length;
    const pageCount = Math.max(1, Math.ceil(total / currentPageSize));

    if (currentPage > pageCount) currentPage = pageCount;

    const start = (currentPage - 1) * currentPageSize;
    const page  = sorted.slice(start, start + currentPageSize);

    grid.innerHTML = "";

    if (page.length === 0) {
      const empty = document.createElement("p");
      empty.className   = "catalog-empty";
      empty.textContent = "Aucun produit trouvé.";
      grid.appendChild(empty);
    } else {
      for (const product of page) {
        grid.appendChild(buildCard(product));
      }
    }

    renderPagination(total, pageCount);
  };

  // Bind events — reset to page 1 on any filter / sort / search change
  if (categoryFilter instanceof HTMLSelectElement) {
    categoryFilter.addEventListener("change", () => {
      currentCategory = categoryFilter.value;
      currentPage = 1;
      render();
    });
  }

  if (sortSelect instanceof HTMLSelectElement) {
    sortSelect.addEventListener("change", () => {
      currentSort = sortSelect.value;
      currentPage = 1;
      render();
    });
  }

  if (searchInput instanceof HTMLInputElement) {
    searchInput.addEventListener("input", () => {
      currentSearch = searchInput.value.trim();
      currentPage = 1;
      render();
    });
  }

  if (pageSizeSelect instanceof HTMLSelectElement) {
    pageSizeSelect.addEventListener("change", () => {
      currentPageSize = parseInt(pageSizeSelect.value, 10);
      currentPage = 1;
      render();
    });
  }

  render();
})();
