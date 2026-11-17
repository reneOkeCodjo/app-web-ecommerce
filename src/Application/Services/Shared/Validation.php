<?php

// On impose des types stricts pour éviter les conversions silencieuses.
declare(strict_types=1);

// Ce service appartient à la couche « Application », dossier Shared.
namespace App\Application\Services\Shared;

// On importe les objets de transfert (DTO) renvoyés après validation.
use App\Application\DTO\LoginRequestDTO;
use App\Application\DTO\ProductRequestDTO;
use App\Application\DTO\RegisterRequestDTO;

// On importe chaque validateur spécialisé.
use App\Application\Validation\Auth\ConfirmPasswordValidator;
use App\Application\Validation\Auth\EmailValidator;
use App\Application\Validation\Auth\LoginPasswordValidator;
use App\Application\Validation\Auth\PasswordValidator;
use App\Application\Validation\Auth\UsernameValidator;
use App\Application\Validation\Product\CategoryValidator as ProductCategoryValidator;
use App\Application\Validation\Product\DateInValidator as ProductDateInValidator;
use App\Application\Validation\Product\DesignationValidator as ProductDesignationValidator;
use App\Application\Validation\Product\ImageValidator as ProductImageValidator;
use App\Application\Validation\Product\PriceValidator as ProductPriceValidator;
use App\Application\Validation\Product\PromotionValidator as ProductPromotionValidator;
use App\Application\Validation\Product\StockValidator as ProductStockValidator;

/**
 * Orchestre les validateurs de champs pour chaque formulaire.
 */
final class Validation implements ValidationInterface
{
    /**
     * On reçoit tous les validateurs, avec une instance par défaut si besoin.
     */
    public function __construct(
        private UsernameValidator $usernameValidator = new UsernameValidator(),
        private PasswordValidator $passwordValidator = new PasswordValidator(),
        private LoginPasswordValidator $loginPasswordValidator = new LoginPasswordValidator(),
        private EmailValidator $emailValidator = new EmailValidator(),
        private ConfirmPasswordValidator $confirmPasswordValidator = new ConfirmPasswordValidator(),
        private ProductCategoryValidator $productCategoryValidator = new ProductCategoryValidator(),
        private ProductDesignationValidator $productDesignationValidator = new ProductDesignationValidator(),
        private ProductPriceValidator $productPriceValidator = new ProductPriceValidator(),
        private ProductStockValidator $productStockValidator = new ProductStockValidator(),
        private ProductDateInValidator $productDateInValidator = new ProductDateInValidator(),
        private ProductPromotionValidator $productPromotionValidator = new ProductPromotionValidator(),
        private ProductImageValidator $productImageValidator = new ProductImageValidator()
    ) {
    }

    /**
     * Valide les champs de connexion.
     */
    public function validateLogin(mixed $username, mixed $password): ValidationResult
    {
        // On prépare la liste des erreurs.
        $errors = [];

        // On vérifie le nom de connexion.
        $usernameResult = $this->usernameValidator->validate($username);
        if (!$usernameResult->isValid()) {
            $errors['user_login'] = (string) $usernameResult->getError();
        }

        // On vérifie le mot de passe.
        $passwordResult = $this->loginPasswordValidator->validate($password);
        if (!$passwordResult->isValid()) {
            $errors['user_password'] = (string) $passwordResult->getError();
        }

        // S'il y a des erreurs, on les renvoie.
        if ($errors !== []) {
            return new ValidationResult($errors);
        }

        // Sinon, on renvoie un objet de connexion prêt à l'emploi.
        return new ValidationResult(
            [],
            new LoginRequestDTO(
                (string) $usernameResult->getValue(),
                (string) $passwordResult->getValue()
            )
        );
    }

    /**
     * Valide les champs d'inscription.
     */
    public function validateRegister(
        mixed $username,
        mixed $email,
        mixed $password,
        mixed $passwordConfirm
    ): ValidationResult {
        $errors = [];

        // Nom de connexion.
        $usernameResult = $this->usernameValidator->validate($username);
        if (!$usernameResult->isValid()) {
            $errors['user_login'] = (string) $usernameResult->getError();
        }

        // Adresse e-mail.
        $emailResult = $this->emailValidator->validate($email);
        if (!$emailResult->isValid()) {
            $errors['user_mail'] = (string) $emailResult->getError();
        }

        // Mot de passe.
        $passwordResult = $this->passwordValidator->validate($password);
        if (!$passwordResult->isValid()) {
            $errors['user_password'] = (string) $passwordResult->getError();
        }

        // Confirmation du mot de passe.
        $confirmResult = $this->confirmPasswordValidator->validate($password, $passwordConfirm);
        if (!$confirmResult->isValid()) {
            $errors['password_confirm'] = (string) $confirmResult->getError();
        }

        if ($errors !== []) {
            return new ValidationResult($errors);
        }

        // On renvoie l'objet d'inscription validé.
        return new ValidationResult(
            [],
            null,
            new RegisterRequestDTO(
                (string) $usernameResult->getValue(),
                (string) $emailResult->getValue(),
                (string) $passwordResult->getValue()
            )
        );
    }

    /**
     * Valide les champs d'un produit.
     */
    public function validateProduct(
        mixed $type,
        mixed $designation,
        mixed $priceHt,
        mixed $stock,
        mixed $dateIn,
        mixed $promotion = null,
        mixed $image = null
    ): ValidationResult {
        $errors = [];

        // Catégorie.
        $typeResult = $this->productCategoryValidator->validate($type);
        if (!$typeResult->isValid()) {
            $errors['type_p'] = (string) $typeResult->getError();
        }

        // Désignation.
        $designationResult = $this->productDesignationValidator->validate($designation);
        if (!$designationResult->isValid()) {
            $errors['designation_p'] = (string) $designationResult->getError();
        }

        // Prix HT.
        $priceResult = $this->productPriceValidator->validate($priceHt);
        if (!$priceResult->isValid()) {
            $errors['prix_ht'] = (string) $priceResult->getError();
        }

        // Stock.
        $stockResult = $this->productStockValidator->validate($stock);
        if (!$stockResult->isValid()) {
            $errors['stock_p'] = (string) $stockResult->getError();
        }

        // Date d'entrée.
        $dateResult = $this->productDateInValidator->validate($dateIn);
        if (!$dateResult->isValid()) {
            $errors['date_in'] = (string) $dateResult->getError();
        }

        // Promotion.
        $promotionResult = $this->productPromotionValidator->validate($promotion);
        if (!$promotionResult->isValid()) {
            $errors['ppromo'] = (string) $promotionResult->getError();
        }

        // Image.
        $imageResult = $this->productImageValidator->validate($image);
        if (!$imageResult->isValid()) {
            $errors['product_image'] = (string) $imageResult->getError();
        }

        if ($errors !== []) {
            return new ValidationResult($errors);
        }

        // On renvoie l'objet produit validé.
        return new ValidationResult(
            [],
            null,
            null,
            new ProductRequestDTO(
                (string) $typeResult->getValue(),
                (string) $designationResult->getValue(),
                (string) $priceResult->getValue(),
                (string) $stockResult->getValue(),
                (string) $dateResult->getValue(),
                $promotionResult->getValue() !== null
                    ? (string) $promotionResult->getValue()
                    : null,
                $imageResult->getValue() !== null
                    ? (string) $imageResult->getValue()
                    : null
            )
        );
    }
}
