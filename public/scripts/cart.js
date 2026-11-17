(function () {
  "use strict";

  const content = document.getElementById("cart-content");
  if (!content) return;

  // Centralise le format d'affichage de tous les montants.
  const formatPrice = (value) => Number(value).toFixed(2) + " €";

  const postCartAction = async (data) => {
    // Les changements sont envoyés au serveur, qui recalcule les vrais totaux.
    const response = await fetch("cart.php", {
      method: "POST",
      headers: { "Content-Type": "application/x-www-form-urlencoded" },
      body: new URLSearchParams(data),
    });
    const result = await response.json();
    if (!response.ok || !result.ok) {
      throw new Error(result.error || "Erreur panier.");
    }
    return result.cart;
  };

  const render = (cart) => {
    // Reconstruit l'affichage après chaque modification réussie.
    content.innerHTML = "";

    if (cart.items.length === 0) {
      const empty = document.createElement("p");
      empty.className = "catalog-empty";
      empty.textContent = "Votre panier est vide.";
      content.appendChild(empty);
      return;
    }

    const list = document.createElement("div");
    list.className = "cart-list";

    for (const item of cart.items) {
      // Chaque ligne présente le produit, son prix, sa quantité et son total.
      const row = document.createElement("article");
      row.className = "cart-item";

      const name = document.createElement("h2");
      name.className = "cart-item__name";
      name.textContent = item.designation;
      row.appendChild(name);

      const price = document.createElement("span");
      price.textContent = formatPrice(item.unitTtc) + " / unité";
      row.appendChild(price);

      const quantity = document.createElement("input");
      quantity.type = "number";
      quantity.min = "0";
      quantity.max = String(item.stock);
      quantity.value = String(item.quantity);
      quantity.setAttribute("aria-label", "Quantité de " + item.designation);
      quantity.addEventListener("change", async () => {
        // La quantité zéro est interprétée comme une suppression par le service.
        try {
          render(await postCartAction({
            action: "set",
            product_id: item.id,
            quantity: quantity.value,
          }));
        } catch (error) {
          window.alert(error.message);
          render(window.__CART__);
        }
      });
      row.appendChild(quantity);

      const total = document.createElement("strong");
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
        // La suppression passe par le même endpoint serveur.
        try {
          render(await postCartAction({ action: "remove", product_id: item.id }));
        } catch (error) {
          window.alert(error.message);
        }
      });
      row.appendChild(remove);
      list.appendChild(row);
    }
    content.appendChild(list);

    // Affiche les montants HT, la TVA, l'expédition et le total TTC.
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
      totals.append(name, amount);
    });
    content.appendChild(totals);
  };

  render(window.__CART__ || { items: [], totals: {} });
})();
