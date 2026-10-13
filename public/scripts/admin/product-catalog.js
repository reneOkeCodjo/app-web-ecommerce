/**
 * In-memory catalog helpers for the admin products page.
 */

/**
 * @typedef {{
 *   id: number,
 *   type: string,
 *   designation: string,
 *   prixHt: number,
 *   promotion: number | null,
 *   prixTtc: number,
 *   prixDisponible: number,
 *   dateIn: string,
 *   timesIn: string,
 *   stock: number
 * }} AdminProduct
 */

/**
 * @param {unknown} raw
 * @returns {AdminProduct[]}
 */
export function readProducts(raw) {
  return Array.isArray(raw) ? raw : [];
}

/**
 * @param {AdminProduct[]} products
 * @returns {Map<string, AdminProduct>}
 */
export function indexProductsById(products) {
  return new Map(products.map((product) => [String(product.id), product]));
}
