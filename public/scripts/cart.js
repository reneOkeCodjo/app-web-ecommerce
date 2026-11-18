(function () {
  "use strict";

  const content = document.getElementById("cart-content");
  if (!content) return;

  const feedback = document.getElementById("cart-feedback");
  const csrfToken = content.dataset.csrf || "";

  const moneyFormatter = new Intl.NumberFormat("fr-FR", {
    style: "currency",
    currency: "EUR",
    minimumFractionDigits: 2,
  });

  const formatPrice = (value) => moneyFormatter.format(Number(value) || 0);

  const setFeedback = (message, isError) => {
    if (!(feedback instanceof HTMLElement)) return;
    feedback.textContent = message;
    feedback.classList.toggle("cart-feedback--error", Boolean(isError));
  };

  const readInitialCart = () => {
    const node = document.getElementById("cart-data");
    if (!node) return { items: [], totals: {} };

    try {
      return JSON.parse(node.textContent || "{}");
    } catch (error) {
      return { items: [], totals: {} };
    }
  };

  const postCartAction = async (data) => {
    const response = await fetch("cart.php", {
      method: "POST",
      headers: { "Content-Type": "application/x-www-form-urlencoded" },
      body: new URLSearchParams({ _csrf: csrfToken, ...data }),
    });
    const result = await response.json();
    if (!response.ok || !result.ok) {
      throw new Error(result.error || "Erreur panier.");
    }
    return result.cart;
  };

  let currentCart = readInitialCart();

  const render = (cart) => {
    currentCart = cart;
    content.innerHTML = "";

    if (!Array.isArray(cart.items) || cart.items.length === 0) {
      const empty = document.createElement("div");
      empty.className = "cart-empty";

      const title = document.createElement("p");
      title.className = "cart-empty__title";
      title.textContent = "Votre panier est vide.";

      const link = document.createElement("a");
      link.className = "btn btn--primary";
      link.href = "products.php";
      link.textContent = "Découvrir le catalogue";

      empty.append(title, link);
      content.appendChild(empty);
      return;
    }

    const list = document.createElement("div");
    list.className = "cart-list";

    for (const item of cart.items) {
      const row = document.createElement("article");
      row.className = "cart-item";

      const heading = document.createElement("div");
      heading.className = "cart-item__heading";

      const name = document.createElement("h3");
      name.className = "cart-item__name";
      name.textContent = item.designation;

      const price = document.createElement("span");
      price.className = "cart-item__price";
      price.textContent = formatPrice(item.unitTtc) + " / unité";

      heading.append(name, price);
      row.appendChild(heading);

      const quantity = document.createElement("input");
      quantity.type = "number";
      quantity.min = "0";
      quantity.max = String(item.stock);
      quantity.value = String(item.quantity);
      quantity.setAttribute("aria-label", "Quantité de " + item.designation);
      quantity.addEventListener("change", async () => {
        try {
          render(await postCartAction({
            action: "set",
            product_id: item.id,
            quantity: quantity.value,
          }));
          setFeedback("Quantité mise à jour.", false);
        } catch (error) {
          setFeedback(error.message, true);
          render(currentCart);
        }
      });
      row.appendChild(quantity);

      const total = document.createElement("strong");
      total.className = "cart-item__total";
      total.textContent = formatPrice(item.lineTtc);
      row.appendChild(total);

      const remove = document.createElement("button");
      remove.type = "button";
      remove.className = "cart-item__remove";
      remove.setAttribute("aria-label", "Supprimer " + item.designation);
      remove.title = "Supprimer du panier";
      remove.innerHTML = `
        <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
          <path d="M4 7h16M9 7V4h6v3m-9 0 1 13h8l1-13M10 11v6m4-6v6" />
        </svg>
      `;
      remove.addEventListener("click", async () => {
        try {
          render(await postCartAction({ action: "remove", product_id: item.id }));
          setFeedback("Article retiré du panier.", false);
        } catch (error) {
          setFeedback(error.message, true);
        }
      });
      row.appendChild(remove);
      list.appendChild(row);
    }
    content.appendChild(list);

    const totals = document.createElement("dl");
    totals.className = "cart-totals";
    [
      ["Sous-total HT", cart.totals.subtotalHt],
      ["TVA (20 %)", cart.totals.vat],
      ["Sous-total TTC", cart.totals.subtotalTtc],
      ["Expédition", cart.totals.shipping],
      ["Total", cart.totals.total],
    ].forEach(([label, value]) => {
      const name = document.createElement("dt");
      name.textContent = label;
      const amount = document.createElement("dd");
      amount.textContent = formatPrice(value);
      if (label === "Total") {
        name.classList.add("cart-totals__grand-total");
        amount.classList.add("cart-totals__grand-total");
      }
      totals.append(name, amount);
    });
    content.appendChild(totals);
  };

  render(currentCart);
})();
