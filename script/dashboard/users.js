/**
 * Gestion des utilisateurs : formulaire d'ajout + dialog de détail (vue / édition / suppression).
 * Les contrôles e-mail et mot de passe réutilisent `window.FormValidation`
 * (`script/common/validation.js`).
 * À la mise à jour, un mot de passe vide est autorisé (le serveur conserve le hash existant).
 */

/**
 * @typedef {Object} UserSnapshot
 * @property {string} id
 * @property {string} name
 * @property {string} email
 * @property {string} compteId
 * @property {string} [compte]
 */

/**
 * @typedef {Object} UserFormOptions
 * @property {boolean} passwordRequired - `true` à l'ajout, `false` à la mise à jour (vide = conserver)
 */

const { validateEmail, validatePassword, setFieldState } = window.FormValidation;

const userDetailDialog = document.querySelector('#user-detail-dialog');
const userDetailForm = document.querySelector('#user-detail-form');
const userDetailTitle = document.querySelector('#user-detail-title');
const detailUserActionInput = document.querySelector('#detail_user_form_action');
const detailUserFields = [
    document.querySelector('#detail_user_username'),
    document.querySelector('#detail_user_email'),
    document.querySelector('#detail_user_password'),
];
const detailUserCompte = document.querySelector('#detail_user_compte_id');
const userViewActions = document.querySelector('#user-detail-actions-view');
const userEditActions = document.querySelector('#user-detail-actions-edit');
const editUserBtn = document.querySelector('#edit-user-btn');
const deleteUserBtn = document.querySelector('#delete-user-btn');
const closeUserDetailBtn = document.querySelector('#close-user-detail-btn');
const cancelEditUserBtn = document.querySelector('#cancel-edit-user-btn');
const deleteUserConfirmDialog = document.querySelector('#delete-user-confirm-dialog');
const deleteUserConfirmMessage = document.querySelector('#delete-user-confirm-message');
const confirmDeleteUserBtn = document.querySelector('#confirm-delete-user-btn');
const cancelDeleteUserBtn = document.querySelector('#cancel-delete-user-btn');
const userRows = document.querySelectorAll('.user-row');
const addUserForm = document.querySelector('#add-user-form');

/** @type {UserSnapshot|null} Instantané du dernier utilisateur ouvert (restauration à l'annulation) */
let currentUser = null;

/**
 * Efface les couleurs de bordure et les messages `.field-error` d'un formulaire.
 * @param {HTMLFormElement} form
 * @returns {void}
 */
function clearFormFieldStates(form) {
    form.querySelectorAll('.form-field input, .form-field select').forEach((input) => {
        input.style.borderColor = '';
        const error = input.closest('.form-field')?.querySelector('.field-error');
        if (error) error.textContent = '';
    });
}

/**
 * Validation partagée pour les formulaires d'ajout et de mise à jour d'utilisateur.
 * Met à jour l'état UI de chaque champ via `setFieldState`.
 * @param {HTMLFormElement} form
 * @param {UserFormOptions} options
 * @returns {boolean} `true` si tous les champs contrôlés sont valides
 */
function validateUserForm(form, { passwordRequired }) {
    let isValid = true;

    const usernameInput = form.querySelector('input[name="username"]');
    const emailInput = form.querySelector('input[name="email"]');
    const passwordInput = form.querySelector('input[name="password"]');
    const compteSelect = form.querySelector('select[name="compte_id"]');

    if (!usernameInput.value.trim()) {
        setFieldState(usernameInput, 'This field is required');
        isValid = false;
    } else {
        setFieldState(usernameInput);
    }

    const emailResult = validateEmail(emailInput.value);
    setFieldState(emailInput, emailResult.message);
    if (!emailResult.valid) isValid = false;

    const passwordResult = validatePassword(passwordInput.value, {
        required: passwordRequired,
    });
    setFieldState(passwordInput, passwordResult.message);
    if (!passwordResult.valid) isValid = false;

    if (compteSelect && !compteSelect.value) {
        setFieldState(compteSelect, 'This field is required');
        isValid = false;
    } else if (compteSelect) {
        setFieldState(compteSelect);
    }

    return isValid;
}

// --- Formulaire d'ajout d'utilisateur ---
if (addUserForm) {
    const addEmailInput = addUserForm.querySelector('input[name="email"]');
    const addPasswordInput = addUserForm.querySelector('input[name="password"]');

    /**
     * Valide en direct le champ e-mail du formulaire d'ajout.
     * @returns {void}
     */
    function handleAddEmailInput() {
        const result = validateEmail(addEmailInput.value);
        setFieldState(addEmailInput, result.message);
    }

    /**
     * Valide en direct le champ mot de passe du formulaire d'ajout (obligatoire).
     * @returns {void}
     */
    function handleAddPasswordInput() {
        const result = validatePassword(addPasswordInput.value, { required: true });
        setFieldState(addPasswordInput, result.message);
    }

    /**
     * Valide puis soumet le formulaire d'ajout d'utilisateur.
     * @param {SubmitEvent} event
     * @returns {void}
     */
    function handleAddUserSubmit(event) {
        event.preventDefault();
        if (!validateUserForm(addUserForm, { passwordRequired: true })) return;
        addUserForm.submit();
    }

    addEmailInput?.addEventListener('input', handleAddEmailInput);
    addPasswordInput?.addEventListener('input', handleAddPasswordInput);
    addUserForm.addEventListener('submit', handleAddUserSubmit);
}

// --- Dialog de détail utilisateur (vue / édition / suppression) ---
if (userDetailDialog && userDetailForm) {
    const detailEmailInput = document.querySelector('#detail_user_email');
    const detailPasswordInput = document.querySelector('#detail_user_password');

    /**
     * Bascule les champs en lecture seule et les boutons d'action (vue vs édition).
     * @param {'view'|'edit'} mode
     * @returns {void}
     */
    function setUserDetailMode(mode) {
        const isEdit = mode === 'edit';
        detailUserFields.forEach((field) => {
            if (!field) return;
            field.readOnly = !isEdit;
        });
        if (detailUserCompte) {
            detailUserCompte.disabled = !isEdit;
        }
        userViewActions?.classList.toggle('hidden', isEdit);
        userEditActions?.classList.toggle('hidden', !isEdit);
        userDetailTitle.textContent = isEdit ? 'Update user' : 'User details';
        detailUserActionInput.value = 'update_user';
        userDetailForm.classList.toggle('editing', isEdit);
        if (!isEdit) {
            clearFormFieldStates(userDetailForm);
        }
    }

    /**
     * Remplit les champs du formulaire de détail à partir d'un instantané utilisateur.
     * Le mot de passe est volontairement laissé vide (vide = conserver le hash côté serveur).
     * @param {UserSnapshot} user
     * @returns {void}
     */
    function fillUserDetail(user) {
        document.querySelector('#detail_user_id').value = user.id;
        document.querySelector('#detail_user_username').value = user.name;
        document.querySelector('#detail_user_email').value = user.email;
        document.querySelector('#detail_user_password').value = '';
        if (detailUserCompte) {
            detailUserCompte.value = user.compteId;
        }
        clearFormFieldStates(userDetailForm);
    }

    /**
     * Construit un instantané depuis une ligne du tableau et ouvre la dialog en mode vue.
     * @param {HTMLElement} row - Une `.user-row` avec des attributs `data-*`
     * @returns {void}
     */
    function openUserDetail(row) {
        currentUser = {
            id: row.dataset.id,
            name: row.dataset.name,
            email: row.dataset.email,
            compteId: row.dataset.compteId,
            compte: row.dataset.compte,
        };
        fillUserDetail(currentUser);
        setUserDetailMode('view');
        userDetailDialog.showModal();
    }

    /**
     * Valide en direct le champ e-mail pendant l'édition d'un utilisateur.
     * @returns {void}
     */
    function handleDetailEmailInput() {
        if (detailEmailInput.readOnly) return;
        const result = validateEmail(detailEmailInput.value);
        setFieldState(detailEmailInput, result.message);
    }

    /**
     * Valide en direct le champ mot de passe pendant l'édition d'un utilisateur.
     * Un mot de passe vide est autorisé (conserver le hash existant).
     * @returns {void}
     */
    function handleDetailPasswordInput() {
        if (detailPasswordInput.readOnly) return;
        const result = validatePassword(detailPasswordInput.value, { required: false });
        setFieldState(detailPasswordInput, result.message);
    }

    /**
     * Valide puis soumet le formulaire de mise à jour.
     * N'intercepte pas la soumission lorsque l'action est `delete_user`.
     * @param {SubmitEvent} event
     * @returns {void}
     */
    function handleUserDetailSubmit(event) {
        if (detailUserActionInput.value === 'delete_user') return;

        event.preventDefault();
        if (!validateUserForm(userDetailForm, { passwordRequired: false })) return;
        // Les selects désactivés ne sont pas envoyés ; on les réactive avant le POST
        if (detailUserCompte) {
            detailUserCompte.disabled = false;
        }
        userDetailForm.submit();
    }

    /**
     * Passe la dialog de détail en mode édition.
     * @returns {void}
     */
    function handleEditUser() {
        setUserDetailMode('edit');
    }

    /**
     * Annule l'édition : restaure les valeurs d'origine et revient en mode vue.
     * @returns {void}
     */
    function handleCancelEditUser() {
        if (currentUser) {
            fillUserDetail(currentUser);
        }
        setUserDetailMode('view');
    }

    /**
     * Ferme la dialog de détail utilisateur.
     * @returns {void}
     */
    function handleCloseUserDetail() {
        userDetailDialog.close();
    }

    /**
     * Ouvre la dialog de confirmation de suppression pour l'utilisateur courant.
     * @returns {void}
     */
    function handleDeleteUserClick() {
        if (!currentUser || !deleteUserConfirmDialog) return;
        deleteUserConfirmMessage.textContent =
            `Are you sure you want to delete "${currentUser.name}" (#${currentUser.id})? This cannot be undone.`;
        deleteUserConfirmDialog.showModal();
    }

    /**
     * Ferme la dialog de confirmation sans supprimer.
     * @returns {void}
     */
    function handleCancelDeleteUser() {
        deleteUserConfirmDialog?.close();
    }

    /**
     * Confirme la suppression : pose `form_submitted_value` à `delete_user` et soumet.
     * @returns {void}
     */
    function handleConfirmDeleteUser() {
        if (!currentUser) return;
        detailUserActionInput.value = 'delete_user';
        deleteUserConfirmDialog?.close();
        userDetailForm.submit();
    }

    /**
     * Ferme la dialog de suppression au clic sur le fond.
     * @param {MouseEvent} event
     * @returns {void}
     */
    function handleDeleteUserConfirmBackdrop(event) {
        if (event.target === deleteUserConfirmDialog) {
            deleteUserConfirmDialog.close();
        }
    }

    /**
     * Ferme la dialog de détail utilisateur au clic sur le fond.
     * @param {MouseEvent} event
     * @returns {void}
     */
    function handleUserDetailBackdrop(event) {
        if (event.target === userDetailDialog) {
            userDetailDialog.close();
        }
    }

    /**
     * Repasse en mode vue à chaque fermeture de la dialog de détail.
     * @returns {void}
     */
    function handleUserDetailClose() {
        setUserDetailMode('view');
    }

    detailEmailInput?.addEventListener('input', handleDetailEmailInput);
    detailPasswordInput?.addEventListener('input', handleDetailPasswordInput);
    userDetailForm.addEventListener('submit', handleUserDetailSubmit);

    userRows.forEach((row) => {
        row.addEventListener('click', () => openUserDetail(row));
    });

    editUserBtn?.addEventListener('click', handleEditUser);
    cancelEditUserBtn?.addEventListener('click', handleCancelEditUser);
    closeUserDetailBtn?.addEventListener('click', handleCloseUserDetail);
    deleteUserBtn?.addEventListener('click', handleDeleteUserClick);
    cancelDeleteUserBtn?.addEventListener('click', handleCancelDeleteUser);
    confirmDeleteUserBtn?.addEventListener('click', handleConfirmDeleteUser);
    deleteUserConfirmDialog?.addEventListener('click', handleDeleteUserConfirmBackdrop);
    userDetailDialog.addEventListener('click', handleUserDetailBackdrop);
    userDetailDialog.addEventListener('close', handleUserDetailClose);
}
