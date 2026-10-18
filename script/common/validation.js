/**
 * Helpers de validation de formulaires partagés.
 * Exposés via `window.FormValidation` pour être réutilisés par les scripts
 * d'authentification et du dashboard sans bundler (balises `<script>` classiques).
 *
 * Utilisé par : script/auth/register.js, script/dashboard/users.js
 *
 * @namespace FormValidation
 */
window.FormValidation = (() => {
    const EMAIL_REGEX = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

    /**
     * @typedef {Object} PasswordRule
     * @property {string} id - Identifiant DOM du `<li>` correspondant dans la checklist d'inscription
     * @property {string} label - Message affiché en cas d'échec
     * @property {function(string): boolean} test - Retourne `true` si la valeur respecte la règle
     */

    /**
     * @typedef {Object} ValidationResult
     * @property {boolean} valid - Indique si la valeur a passé la validation
     * @property {string} message - Message d'erreur, ou chaîne vide si valide
     */

    /**
     * @typedef {Object} ValidationOptions
     * @property {boolean} [required=true] - Si `false`, une valeur vide est acceptée
     */

    /**
     * Règles de force du mot de passe.
     * Doivent rester alignées avec la liste des règles dans index.php (formulaire d'inscription).
     * @type {PasswordRule[]}
     */
    const PASSWORD_RULES = [
        {
            id: 'password-rule-length',
            label: 'At least 8 characters long',
            test: (v) => v.length >= 8,
        },
        {
            id: 'password-rule-uppercase',
            label: 'At least one uppercase letter',
            test: (v) => /[A-Z]/.test(v),
        },
        {
            id: 'password-rule-lowercase',
            label: 'At least one lowercase letter',
            test: (v) => /[a-z]/.test(v),
        },
        {
            id: 'password-rule-number',
            label: 'At least one number',
            test: (v) => /[0-9]/.test(v),
        },
        {
            id: 'password-rule-special',
            label: 'At least one special character',
            test: (v) => /[!@#$%^&*]/.test(v),
        },
    ];

    /**
     * Vérifie si une chaîne ressemble à une adresse e-mail valide.
     * @param {string} value
     * @returns {boolean}
     */
    const isValidEmail = (value) => EMAIL_REGEX.test(String(value).trim());

    /**
     * Retourne les règles de mot de passe non respectées par la valeur donnée.
     * @param {string} value
     * @returns {PasswordRule[]}
     */
    const getFailedPasswordRules = (value) =>
        PASSWORD_RULES.filter((rule) => !rule.test(String(value)));

    /**
     * Vérifie si un mot de passe respecte toutes les règles de PASSWORD_RULES.
     * @param {string} value
     * @returns {boolean}
     */
    const isValidPassword = (value) => getFailedPasswordRules(value).length === 0;

    /**
     * Compare le mot de passe et sa confirmation.
     * @param {string} password
     * @param {string} confirmPassword
     * @returns {boolean}
     */
    const passwordsMatch = (password, confirmPassword) =>
        String(password) === String(confirmPassword);

    /**
     * Valide un mot de passe selon PASSWORD_RULES.
     * Passer `{ required: false }` quand un champ vide signifie « conserver l'existant »
     * (mise à jour utilisateur).
     * @param {string} value
     * @param {ValidationOptions} [options]
     * @returns {ValidationResult}
     */
    const validatePassword = (value, options = {}) => {
        const required = options.required !== false;
        const trimmed = String(value ?? '');

        if (!trimmed.trim()) {
            if (!required) {
                return { valid: true, message: '' };
            }
            return { valid: false, message: 'This field is required' };
        }

        const failed = getFailedPasswordRules(trimmed);
        if (failed.length) {
            return { valid: false, message: failed[0].label };
        }

        return { valid: true, message: '' };
    };

    /**
     * Valide une adresse e-mail (obligatoire par défaut).
     * @param {string} value
     * @param {ValidationOptions} [options]
     * @returns {ValidationResult}
     */
    const validateEmail = (value, options = {}) => {
        const required = options.required !== false;
        const trimmed = String(value ?? '').trim();

        if (!trimmed) {
            if (!required) {
                return { valid: true, message: '' };
            }
            return { valid: false, message: 'This field is required' };
        }

        if (!isValidEmail(trimmed)) {
            return { valid: false, message: 'Invalid email address' };
        }

        return { valid: true, message: '' };
    };

    /**
     * Met à jour la couleur de bordure et le texte `.field-error` sous un champ.
     * Markup attendu : `.form-field` > input + `.field-error`.
     * @param {HTMLElement|null} input
     * @param {string} [message=''] - Chaîne vide pour effacer l'erreur et marquer le champ valide
     * @returns {void}
     */
    const setFieldState = (input, message = '') => {
        if (!input) return;
        const field = input.closest('.form-field');
        const error = field?.querySelector('.field-error');
        input.style.borderColor = message ? 'red' : 'green';
        if (error) {
            error.textContent = message;
        }
    };

    /**
     * Colorie la checklist des règles de mot de passe (vert = ok, rouge = échec).
     * @param {ParentNode|null} root - En général le formulaire contenant les `#password-rule-*`
     * @param {string} value - Valeur actuelle du mot de passe à évaluer
     * @returns {void}
     */
    const syncPasswordRules = (root, value) => {
        if (!root) return;
        PASSWORD_RULES.forEach((rule) => {
            const item = root.querySelector(`#${rule.id}`);
            if (!item) return;
            item.style.color = rule.test(String(value)) ? 'green' : 'red';
        });
    };

    return {
        PASSWORD_RULES,
        isValidEmail,
        isValidPassword,
        passwordsMatch,
        getFailedPasswordRules,
        validatePassword,
        validateEmail,
        setFieldState,
        syncPasswordRules,
    };
})();
