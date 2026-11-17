/**
 * Client-side mirror of App\Domain\Pricing\ProductPricing (TVA 20 %).
 */

/**
 * Lit la balise JSON du taux de TVA injectée par PHP.
 * @returns {number}
 */
function readTvaRate() {
  const node = document.getElementById("admin-tva-rate");
  if (!node) return 0.2;
  try {
    const value = JSON.parse(node.textContent || "");
    return typeof value === "number" ? value : 0.2;
  } catch (error) {
    return 0.2;
  }
}

export const TVA_RATE = readTvaRate();

/**
 * @param {number} priceHt
 * @returns {number}
 */
export function priceTtc(priceHt) {
  return Math.round(priceHt * (1 + TVA_RATE) * 100) / 100;
}

/**
 * @param {number} priceHt
 * @param {number | null | undefined} promotionPercent
 * @returns {number}
 */
export function priceAvailable(priceHt, promotionPercent) {
  const ttc = priceTtc(priceHt);
  if (promotionPercent == null || promotionPercent <= 0) {
    return ttc;
  }
  return Math.round(ttc * (1 - promotionPercent / 100) * 100) / 100;
}

/**
 * @param {number | null | undefined} promotionPercent
 * @returns {boolean}
 */
export function hasPromotion(promotionPercent) {
  return promotionPercent != null && promotionPercent > 0;
}

/**
 * @param {number} value
 * @returns {string}
 */
export function formatMoney(value) {
  return value.toFixed(2);
}
