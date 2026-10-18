/**
 * Formulaire d'inscription : validation en direct + contrôle complet à la soumission.
 * S'appuie sur `window.FormValidation` (`script/common/validation.js`).
 */
const { validateEmail, validatePassword, passwordsMatch, setFieldState, syncPasswordRules } =
    window.FormValidation;

const registerForm = document.querySelector('#register-form');
if (registerForm) {
    const passwordInput = registerForm.querySelector('input[name="password"]');
    const confirmInput = registerForm.querySelector('input[name="confirm-password"]');
    const emailInput = registerForm.querySelector('input[name="email"]');

    /**
     * Valide en direct le champ e-mail (couleur de bordure uniquement).
     * @returns {void}
     */
    function handleEmailInput() {
        const result = validateEmail(emailInput.value);
        emailInput.style.borderColor = result.valid ? 'green' : 'red';
    }

    /**
     * Valide en direct le mot de passe et synchronise la checklist des règles.
     * Recontrôle aussi la confirmation si elle a déjà une valeur.
     * @returns {void}
     */
    function handlePasswordInput() {
        syncPasswordRules(registerForm, passwordInput.value);
        const result = validatePassword(passwordInput.value);
        setFieldState(passwordInput, result.message);
        if (confirmInput.value && !passwordsMatch(passwordInput.value, confirmInput.value)) {
            setFieldState(confirmInput, 'Passwords do not match');
        }
    }

    /**
     * Valide en direct la confirmation par rapport au mot de passe.
     * @returns {void}
     */
    function handleConfirmInput() {
        if (!confirmInput.value.trim()) {
            setFieldState(confirmInput, 'This field is required');
            return;
        }
        setFieldState(
            confirmInput,
            passwordsMatch(passwordInput.value, confirmInput.value) ? '' : 'Passwords do not match'
        );
    }

    /**
     * Valide un champ du formulaire d'inscription par son `name` et met à jour l'UI.
     * @param {string} name - Attribut `name` (`email`, `username`, `password`, `confirm-password`)
     * @returns {boolean} Indique si le champ est valide
     */
    function validateRegisterField(name) {
        const input = registerForm.querySelector(`input[name="${name}"]`);

        if (name === 'email') {
            const result = validateEmail(input.value);
            setFieldState(input, result.message);
            return result.valid;
        }

        if (name === 'password') {
            syncPasswordRules(registerForm, input.value);
            const result = validatePassword(input.value);
            setFieldState(input, result.message);
            return result.valid;
        }

        if (name === 'confirm-password') {
            if (!input.value.trim()) {
                setFieldState(input, 'This field is required');
                return false;
            }
            if (!passwordsMatch(passwordInput.value, input.value)) {
                setFieldState(input, 'Passwords do not match');
                return false;
            }
            setFieldState(input);
            return true;
        }

        // username (et tout autre champ obligatoire simple)
        if (!input.value.trim()) {
            setFieldState(input, 'This field is required');
            return false;
        }

        setFieldState(input);
        return true;
    }

    /**
     * Lance la validation complète ; n'envoie le POST que si tous les champs passent.
     * @param {SubmitEvent} event
     * @returns {void}
     */
    function handleRegisterSubmit(event) {
        event.preventDefault();
        let isValid = true;

        ['email', 'username', 'password', 'confirm-password'].forEach((name) => {
            if (!validateRegisterField(name)) {
                isValid = false;
            }
        });

        if (isValid) {
            registerForm.submit();
        }
    }

    emailInput.addEventListener('input', handleEmailInput);
    passwordInput.addEventListener('input', handlePasswordInput);
    confirmInput.addEventListener('input', handleConfirmInput);
    registerForm.addEventListener('submit', handleRegisterSubmit);
}
