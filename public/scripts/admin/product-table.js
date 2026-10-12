/**
 * Table row interactions (open product view / create form).
 */

/**
 * @param {{
 *   onCreate: () => void,
 *   onView: (productId: string) => void
 * }} handlers
 */
export function bindProductTableTriggers({ onCreate, onView }) {
  document.querySelectorAll("[data-open-dialog]").forEach((trigger) => {
    trigger.addEventListener("click", () => {
      const dialogId = trigger.getAttribute("data-open-dialog");
      const mode = trigger.getAttribute("data-form-mode");
      const productId = trigger.getAttribute("data-product-id");

      if (dialogId === "product-form-dialog" && mode === "create") {
        onCreate();
        return;
      }

      if (dialogId === "product-view-dialog" && productId) {
        onView(productId);
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
}
