/**
 * Dialog de détail produit : consultation / édition / suppression.
 * Les données de ligne viennent des attributs `data-*` sur `.product-row` dans `dashboard.php`.
 * `form_submitted_value` est positionné sur `update_product` ou `delete_product` avant le POST.
 */

/**
 * @typedef {Object} ProductSnapshot
 * @property {string} id
 * @property {string} type
 * @property {string} name
 * @property {string} price
 * @property {string} date
 * @property {string} stock
 * @property {string} promotion
 */

const productDetailDialog = document.querySelector('#product-detail-dialog');
const productDetailForm = document.querySelector('#product-detail-form');
const productDetailTitle = document.querySelector('#product-detail-title');
const detailActionInput = document.querySelector('#detail_form_action');
const detailTypeSelect = document.querySelector('#detail_type_p');
const detailFields = [
    document.querySelector('#detail_designation_p'),
    document.querySelector('#detail_prix_ht'),
    document.querySelector('#detail_date_in'),
    document.querySelector('#detail_stock_p'),
    document.querySelector('#detail_promotion_p'),
];
const viewActions = document.querySelector('#product-detail-actions-view');
const editActions = document.querySelector('#product-detail-actions-edit');
const editProductBtn = document.querySelector('#edit-product-btn');
const deleteProductBtn = document.querySelector('#delete-product-btn');
const closeProductDetailBtn = document.querySelector('#close-product-detail-btn');
const cancelEditProductBtn = document.querySelector('#cancel-edit-product-btn');
const deleteConfirmDialog = document.querySelector('#delete-confirm-dialog');
const deleteConfirmMessage = document.querySelector('#delete-confirm-message');
const confirmDeleteBtn = document.querySelector('#confirm-delete-btn');
const cancelDeleteBtn = document.querySelector('#cancel-delete-btn');
const productRows = document.querySelectorAll('.product-row');

/** @type {ProductSnapshot|null} Instantané du dernier produit ouvert (restauration à l'annulation) */
let currentProduct = null;

if (productDetailDialog && productDetailForm) {
    /**
     * Bascule les champs en lecture seule et les boutons d'action (vue vs édition).
     * @param {'view'|'edit'} mode
     * @returns {void}
     */
    function setDetailMode(mode) {
        const isEdit = mode === 'edit';
        detailFields.forEach((field) => {
            if (!field) return;
            field.readOnly = !isEdit;
        });
        if (detailTypeSelect) {
            detailTypeSelect.disabled = !isEdit;
        }
        viewActions?.classList.toggle('hidden', isEdit);
        editActions?.classList.toggle('hidden', !isEdit);
        productDetailTitle.textContent = isEdit ? 'Update product' : 'Product details';
        detailActionInput.value = 'update_product';
        productDetailForm.classList.toggle('editing', isEdit);
    }

    /**
     * Remplit les champs du formulaire de détail à partir d'un instantané produit.
     * Ajoute une `<option>` temporaire si le type n'existe pas dans la liste des catégories.
     * @param {ProductSnapshot} product
     * @returns {void}
     */
    function fillProductDetail(product) {
        document.querySelector('#detail_id_p').value = product.id;
        if (detailTypeSelect) {
            const hasOption = [...detailTypeSelect.options].some(
                (option) => option.value === product.type
            );
            if (product.type && !hasOption) {
                const option = document.createElement('option');
                option.value = product.type;
                option.textContent = product.type;
                detailTypeSelect.appendChild(option);
            }
            detailTypeSelect.value = product.type;
        }
        document.querySelector('#detail_designation_p').value = product.name;
        document.querySelector('#detail_prix_ht').value = product.price;
        document.querySelector('#detail_date_in').value = product.date;
        document.querySelector('#detail_stock_p').value = product.stock;
        document.querySelector('#detail_promotion_p').value = product.promotion ?? 0;
    }

    /**
     * Réactive le select de type avant le POST (un champ désactivé n'est pas envoyé).
     * @returns {void}
     */
    function handleProductFormSubmit() {
        if (detailTypeSelect) {
            detailTypeSelect.disabled = false;
        }
    }

    /**
     * Construit un instantané depuis une ligne du tableau et ouvre la dialog en mode vue.
     * @param {HTMLElement} row - Une `.product-row` avec des attributs `data-*`
     * @returns {void}
     */
    function openProductDetail(row) {
        currentProduct = {
            id: row.dataset.id,
            type: row.dataset.type,
            name: row.dataset.name,
            price: row.dataset.price,
            date: row.dataset.date,
            stock: row.dataset.stock,
            promotion: row.dataset.promotion ?? '0',
        };
        fillProductDetail(currentProduct);
        setDetailMode('view');
        productDetailDialog.showModal();
    }

    /**
     * Passe la dialog de détail en mode édition.
     * @returns {void}
     */
    function handleEditProduct() {
        setDetailMode('edit');
    }

    /**
     * Annule l'édition : restaure les valeurs d'origine et revient en mode vue.
     * @returns {void}
     */
    function handleCancelEditProduct() {
        if (currentProduct) {
            fillProductDetail(currentProduct);
        }
        setDetailMode('view');
    }

    /**
     * Ferme la dialog de détail produit.
     * @returns {void}
     */
    function handleCloseProductDetail() {
        productDetailDialog.close();
    }

    /**
     * Ouvre la dialog de confirmation de suppression pour le produit courant.
     * @returns {void}
     */
    function handleDeleteProductClick() {
        if (!currentProduct || !deleteConfirmDialog) return;
        deleteConfirmMessage.textContent =
            `Are you sure you want to delete "${currentProduct.name}" (#${currentProduct.id})? This cannot be undone.`;
        deleteConfirmDialog.showModal();
    }

    /**
     * Ferme la dialog de confirmation sans supprimer.
     * @returns {void}
     */
    function handleCancelDelete() {
        deleteConfirmDialog?.close();
    }

    /**
     * Confirme la suppression : pose `form_submitted_value` à `delete_product` et soumet.
     * @returns {void}
     */
    function handleConfirmDelete() {
        if (!currentProduct) return;
        detailActionInput.value = 'delete_product';
        deleteConfirmDialog?.close();
        productDetailForm.submit();
    }

    /**
     * Ferme la dialog de suppression au clic sur le fond.
     * @param {MouseEvent} event
     * @returns {void}
     */
    function handleDeleteConfirmBackdrop(event) {
        if (event.target === deleteConfirmDialog) {
            deleteConfirmDialog.close();
        }
    }

    /**
     * Ferme la dialog de détail produit au clic sur le fond.
     * @param {MouseEvent} event
     * @returns {void}
     */
    function handleProductDetailBackdrop(event) {
        if (event.target === productDetailDialog) {
            productDetailDialog.close();
        }
    }

    /**
     * Repasse en mode vue à chaque fermeture de la dialog de détail.
     * @returns {void}
     */
    function handleProductDetailClose() {
        setDetailMode('view');
    }

    productDetailForm.addEventListener('submit', handleProductFormSubmit);

    productRows.forEach((row) => {
        row.addEventListener('click', () => openProductDetail(row));
    });

    editProductBtn?.addEventListener('click', handleEditProduct);
    cancelEditProductBtn?.addEventListener('click', handleCancelEditProduct);
    closeProductDetailBtn?.addEventListener('click', handleCloseProductDetail);
    deleteProductBtn?.addEventListener('click', handleDeleteProductClick);
    cancelDeleteBtn?.addEventListener('click', handleCancelDelete);
    confirmDeleteBtn?.addEventListener('click', handleConfirmDelete);
    deleteConfirmDialog?.addEventListener('click', handleDeleteConfirmBackdrop);
    productDetailDialog.addEventListener('click', handleProductDetailBackdrop);
    productDetailDialog.addEventListener('close', handleProductDetailClose);
}
