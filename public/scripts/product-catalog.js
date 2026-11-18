/**
 * Catalogue public : filtres, tri, recherche, pagination et aperçu rapide.
 *
 * Les produits ne sont plus injectés via `window.__…__` (bloqué par la CSP).
 * On les lit dans une balise JSON sécurisée : `#catalog-products-data`.
 */

/** Petit raccourci pour les descriptions de produits. */
/** @typedef {{ id: number, type: string, designation: string, prixHt: number, promotion: number|null, prixTtc: number, prixDisponible: number, dateIn: string, timesIn: string, stock: number, image: string|null }} CatalogProduct */

(function () {
  "use strict";

  /**
   * Lit et analyse une balise JSON de la page.
   * @param {string} id
   * @param {unknown} fallback
   * @returns {unknown}
   */
  function readJson(id, fallback) {
    const node = document.getElementById(id);
    if (!node) return fallback;
    try {
      return JSON.parse(node.textContent || "");
    } catch (error) {
      return fallback;
    }
  }

  /** Liste des produits, lue depuis la balise JSON. */
  const rawProducts = readJson("catalog-products-data", []);
  /** @type {CatalogProduct[]} */
  const products = Array.isArray(rawProducts) ? rawProducts : [];

  /** Éléments de la page. */
  const grid = document.getElementById("catalog-grid");
  const paginationNav = document.getElementById("catalog-pagination");
  const categoryFilter = document.getElementById("catalog-category-filter");
  const sortSelect = document.getElementById("catalog-sort");
  const searchInput = document.getElementById("catalog-search");
  const pageSizeSelect = document.getElementById("catalog-page-size");

  // Sans grille, il n'y a rien à afficher.
  if (!grid) return;

  /** État courant de l'affichage. */
  let currentCategory = "";
  let currentSort = "recent";
  let currentSearch = "";
  let currentPage = 1;
  let currentPageSize = 12;

  /** Formateur de prix français (ex. 1 234,00 €). */
  const moneyFormatter = new Intl.NumberFormat("fr-FR", {
    style: "currency",
    currency: "EUR",
    minimumFractionDigits: 2,
  });

  /** Formate un nombre en prix lisible. */
  const formatPrice = (value) => moneyFormatter.format(value);

  /** Tracés éditoriaux utilisés quand un produit n'a pas encore de photo. */
  const categoryDrawings = {
    Smartphone:
      '<rect x="21" y="10" width="22" height="44" rx="5"/><path d="M28 15h8M31 49h2"/><circle cx="32" cy="18" r="1"/>',
    "Ordinateur portable":
      '<rect x="12" y="14" width="40" height="26" rx="3"/><path d="M8 47h48l-4 6H12zM27 40h10"/>',
    "Casque audio":
      '<path d="M16 38V31a16 16 0 0 1 32 0v7"/><rect x="12" y="35" width="10" height="16" rx="4"/><rect x="42" y="35" width="10" height="16" rx="4"/>',
    "Montre connectée":
      '<rect x="21" y="20" width="22" height="24" rx="6"/><path d="M26 20v-7h12v7M26 44v7h12v-7M32 27v6l4 2"/>',
    Tablette:
      '<rect x="14" y="13" width="36" height="38" rx="4"/><path d="M26 44h12M46 20v10"/>',
    Caméra:
      '<rect x="12" y="22" width="40" height="27" rx="5"/><path d="M23 22l3-6h12l3 6"/><circle cx="32" cy="35" r="8"/><circle cx="32" cy="35" r="3"/>',
  };

  /** Crée une illustration de catégorie (sans emoji). */
  const buildPlaceholderIcon = (type) => {
    const svg = document.createElementNS("http://www.w3.org/2000/svg", "svg");
    svg.setAttribute("viewBox", "0 0 64 64");
    svg.setAttribute("fill", "none");
    svg.setAttribute("stroke", "currentColor");
    svg.setAttribute("stroke-width", "1.25");
    svg.setAttribute("stroke-linecap", "round");
    svg.setAttribute("stroke-linejoin", "round");
    svg.setAttribute("aria-hidden", "true");
    svg.classList.add("catalog-card__illustration");
    svg.innerHTML =
      '<circle class="catalog-card__illustration-orbit" cx="32" cy="32" r="25" stroke-dasharray="2 5"/>' +
      '<circle class="catalog-card__illustration-dot" cx="52" cy="17" r="2" fill="currentColor" stroke="none"/>' +
      (categoryDrawings[type] || categoryDrawings.Smartphone);
    return svg;
  };

  /**
   * Construit la carte visuelle d'un produit.
   * @param {CatalogProduct} product
   * @returns {HTMLElement}
   */
  const buildCard = (product) => {
    const hasPromo = product.promotion !== null && product.promotion > 0;
    const inStock = product.stock > 0;

    const card = document.createElement("article");
    card.className = "catalog-card";
    card.setAttribute("data-product-type", product.type);
    card.setAttribute("data-product-id", String(product.id));
    card.tabIndex = 0;
    card.setAttribute("role", "button");
    card.setAttribute("aria-label", "Voir le détail de " + product.designation);

    // Zone image.
    const imageWrap = document.createElement("div");
    imageWrap.className = "catalog-card__image-wrap";
    if (product.image) {
      const img = document.createElement("img");
      img.className = "catalog-card__image";
      img.src = product.image;
      img.alt = product.designation;
      img.loading = "lazy";
      imageWrap.appendChild(img);
    } else {
      const placeholder = document.createElement("span");
      placeholder.className = "catalog-card__placeholder";
      placeholder.setAttribute("aria-hidden", "true");
      placeholder.appendChild(buildPlaceholderIcon(product.type));
      imageWrap.appendChild(placeholder);
    }

    // Micro-libellé de catégorie posé sur le visuel.
    const type = document.createElement("span");
    type.className = "catalog-card__type";
    type.textContent = product.type;
    imageWrap.appendChild(type);

    // Numéro d'édition pour donner un rythme de catalogue.
    const edition = document.createElement("span");
    edition.className = "catalog-card__edition";
    edition.textContent = String(product.id).padStart(2, "0");
    imageWrap.appendChild(edition);

    card.appendChild(imageWrap);

    // Corps de la carte.
    const body = document.createElement("div");
    body.className = "catalog-card__body";

    const name = document.createElement("h2");
    name.className = "catalog-card__name";
    name.textContent = product.designation;
    body.appendChild(name);

    // Bloc des prix.
    const pricing = document.createElement("div");
    pricing.className = "catalog-card__pricing";

    if (hasPromo) {
      const was = document.createElement("span");
      was.className = "catalog-card__price-was";
      was.textContent = formatPrice(product.prixTtc);
      pricing.appendChild(was);

      const now = document.createElement("span");
      now.className = "catalog-card__price catalog-card__price--promo";
      now.textContent = formatPrice(product.prixDisponible);
      pricing.appendChild(now);
    } else {
      const price = document.createElement("span");
      price.className = "catalog-card__price";
      price.textContent = formatPrice(product.prixDisponible);
      pricing.appendChild(price);
    }
    body.appendChild(pricing);

    // On n'affiche un badge que lorsque le produit est en rupture.
    if (!inStock) {
      const stockBadge = document.createElement("span");
      stockBadge.className = "catalog-card__stock catalog-card__stock--out";
      stockBadge.textContent = "Rupture de stock";
      body.appendChild(stockBadge);
    }

    card.appendChild(body);

    // Clic ou clavier : ouvre l'aperçu rapide.
    const open = () => openQuickView(product);
    card.addEventListener("click", open);
    card.addEventListener("keydown", (event) => {
      if (event.key === "Enter" || event.key === " ") {
        event.preventDefault();
        open();
      }
    });

    return card;
  };

  /** Boîte de dialogue d'aperçu rapide. */
  const quickDialog = document.getElementById("quick-view-dialog");

  /**
   * Remplit et affiche l'aperçu rapide d'un produit.
   * @param {CatalogProduct} product
   */
  const openQuickView = (product) => {
    if (!(quickDialog instanceof HTMLDialogElement)) return;

    const image = quickDialog.querySelector("[data-quick-image]");
    const media = image ? image.parentElement : null;
    const category = quickDialog.querySelector("[data-quick-category]");
    const name = quickDialog.querySelector("[data-quick-name]");
    const price = quickDialog.querySelector("[data-quick-price]");
    const stock = quickDialog.querySelector("[data-quick-stock]");
    const categoryText = quickDialog.querySelector("[data-quick-category-text]");
    const reference = quickDialog.querySelector("[data-quick-id]");
    const dateIn = quickDialog.querySelector("[data-quick-date]");

    if (image instanceof HTMLImageElement && media instanceof HTMLElement) {
      if (product.image) {
        image.src = product.image;
        image.alt = product.designation;
        media.hidden = false;
      } else {
        image.removeAttribute("src");
        image.alt = "";
        media.hidden = true;
      }
    }

    if (category) category.textContent = product.type;
    if (name) name.textContent = product.designation;

    if (price) {
      price.textContent = "";
      if (product.promotion !== null && product.promotion > 0) {
        const was = document.createElement("s");
        was.className = "product-dialog__price-was";
        was.textContent = formatPrice(product.prixTtc);

        const now = document.createElement("span");
        now.className = "product-dialog__price-now";
        now.textContent = formatPrice(product.prixDisponible);

        price.appendChild(was);
        price.appendChild(now);
      } else {
        price.textContent = formatPrice(product.prixDisponible);
      }
    }

    if (stock) stock.textContent = product.stock > 0 ? "En stock" : "Rupture de stock";
    if (categoryText) categoryText.textContent = product.type;
    if (reference) reference.textContent = "#" + product.id;
    if (dateIn) dateIn.textContent = product.dateIn;

    quickDialog.showModal();
  };

  // Fermeture de la fenêtre : boutons et clic sur le fond.
  if (quickDialog instanceof HTMLDialogElement) {
    quickDialog.querySelectorAll("[data-close-dialog]").forEach((button) => {
      button.addEventListener("click", () => quickDialog.close());
    });
    quickDialog.addEventListener("click", (event) => {
      if (event.target === quickDialog) {
        quickDialog.close();
      }
    });
  }

  /**
   * Trie une copie de la liste selon la clé choisie.
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
   * Calcule les numéros de page à afficher (avec « … » pour les trous).
   * @param {number} current
   * @param {number} total
   * @returns {(number|string)[]}
   */
  const getPageNumbers = (current, total) => {
    if (total <= 7) return Array.from({ length: total }, (_, i) => i + 1);
    if (current <= 4) return [1, 2, 3, 4, 5, "...", total];
    if (current >= total - 3) {
      return [1, "...", total - 4, total - 3, total - 2, total - 1, total];
    }
    return [1, "...", current - 1, current, current + 1, "...", total];
  };

  /**
   * Affiche les informations et boutons de pagination.
   * @param {number} totalItems
   * @param {number} pageCount
   */
  const renderPagination = (totalItems, pageCount) => {
    if (!paginationNav) return;
    paginationNav.textContent = "";

    const info = document.createElement("span");
    info.className = "pagination__info";
    info.textContent = `${totalItems} résultat${totalItems !== 1 ? "s" : ""}`;
    paginationNav.appendChild(info);

    if (pageCount <= 1) return;

    const controls = document.createElement("div");
    controls.className = "pagination__controls";

    /**
     * Fabrique un bouton de pagination.
     * @param {string} label
     * @param {number} targetPage
     * @param {boolean} disabled
     * @param {boolean} active
     */
    const makeButton = (label, targetPage, disabled, active) => {
      const button = document.createElement("button");
      button.type = "button";
      button.className =
        "pagination__btn" + (active ? " pagination__btn--active" : "");
      button.textContent = label;
      button.disabled = disabled;
      button.setAttribute("aria-current", active ? "page" : "false");
      if (!disabled && !active) {
        button.addEventListener("click", () => {
          currentPage = targetPage;
          render();
          grid.scrollIntoView({ behavior: "smooth", block: "start" });
        });
      }
      return button;
    };

    controls.appendChild(makeButton("‹", currentPage - 1, currentPage === 1, false));

    for (const pageNumber of getPageNumbers(currentPage, pageCount)) {
      if (pageNumber === "...") {
        const dots = document.createElement("span");
        dots.className = "pagination__dots";
        dots.textContent = "…";
        controls.appendChild(dots);
      } else {
        controls.appendChild(
          makeButton(
            String(pageNumber),
            Number(pageNumber),
            Number(pageNumber) === currentPage,
            Number(pageNumber) === currentPage
          )
        );
      }
    }

    controls.appendChild(
      makeButton("›", currentPage + 1, currentPage === pageCount, false)
    );

    paginationNav.appendChild(controls);
  };

  /** Recalcule et réaffiche la grille et la pagination. */
  const render = () => {
    let filtered = products;

    if (currentCategory !== "") {
      filtered = filtered.filter((product) => product.type === currentCategory);
    }

    if (currentSearch !== "") {
      const query = currentSearch.toLowerCase();
      filtered = filtered.filter((product) =>
        product.designation.toLowerCase().includes(query)
      );
    }

    const sorted = sortProducts(filtered, currentSort);
    const total = sorted.length;
    const pageCount = Math.max(1, Math.ceil(total / currentPageSize));

    if (currentPage > pageCount) currentPage = pageCount;

    const start = (currentPage - 1) * currentPageSize;
    const page = sorted.slice(start, start + currentPageSize);

    grid.textContent = "";

    if (page.length === 0) {
      const empty = document.createElement("p");
      empty.className = "catalog-empty";
      empty.textContent = "Aucun produit trouvé.";
      grid.appendChild(empty);
    } else {
      for (const product of page) {
        grid.appendChild(buildCard(product));
      }
    }

    renderPagination(total, pageCount);
  };

  // Filtre par catégorie.
  if (categoryFilter instanceof HTMLSelectElement) {
    categoryFilter.addEventListener("change", () => {
      currentCategory = categoryFilter.value;
      currentPage = 1;
      render();
    });
  }

  // Tri.
  if (sortSelect instanceof HTMLSelectElement) {
    sortSelect.addEventListener("change", () => {
      currentSort = sortSelect.value;
      currentPage = 1;
      render();
    });
  }

  // Recherche.
  if (searchInput instanceof HTMLInputElement) {
    searchInput.addEventListener("input", () => {
      currentSearch = searchInput.value.trim();
      currentPage = 1;
      render();
    });
  }

  // Nombre de produits par page.
  if (pageSizeSelect instanceof HTMLSelectElement) {
    pageSizeSelect.addEventListener("change", () => {
      currentPageSize = parseInt(pageSizeSelect.value, 10) || 12;
      currentPage = 1;
      render();
    });
  }

  // Premier affichage.
  render();
})();
