<?php

declare(strict_types=1);

namespace App\Application\Services\Shared;

use App\Application\DTO\LoginRequestDTO;
use App\Application\DTO\ProductRequestDTO;
use App\Application\DTO\RegisterRequestDTO;
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

interface ValidationInterface
{
    /**
     * Validate and normalize a login payload.
     *
     * @param mixed $username Raw POST username
     * @param mixed $password Raw POST password
     */
    public function validateLogin(mixed $username, mixed $password): ValidationResult;

    /**
     * Validate and normalize a registration payload.
     *
     * @param mixed $username Raw POST username
     * @param mixed $email Raw POST email
     * @param mixed $password Raw POST password
     * @param mixed $passwordConfirm Raw POST password confirmation
     */
    public function validateRegister(
        mixed $username,
        mixed $email,
        mixed $password,
        mixed $passwordConfirm
    ): ValidationResult;

    /**
     * Validate and normalize a product create/update payload.
     */
    public function validateProduct(
        mixed $type,
        mixed $designation,
        mixed $priceHt,
        mixed $stock,
        mixed $dateIn,
        mixed $promotion = null,
        mixed $image = null
    ): ValidationResult;
}

/**
 * Orchestrates field validators for request payloads.
 */
class Validation implements ValidationInterface
{
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
     * Validate the login payload.
     * @param mixed $username The username.
     * @param mixed $password The password.
     * @return ValidationResult The validation result.
     */
    public function validateLogin(mixed $username, mixed $password): ValidationResult
    {
        $errors = [];

        $usernameResult = $this->usernameValidator->validate($username);
        if (!$usernameResult->isValid()) {
            $errors['user_login'] = (string) $usernameResult->getError();
        }

        $passwordResult = $this->loginPasswordValidator->validate($password);
        if (!$passwordResult->isValid()) {
            $errors['user_password'] = (string) $passwordResult->getError();
        }

        if ($errors !== []) {
            return new ValidationResult($errors);
        }

        return new ValidationResult(
            [],
            new LoginRequestDTO(
                (string) $usernameResult->getValue(),
                (string) $passwordResult->getValue()
            )
        );
    }

    public function validateRegister(
        mixed $username,
        mixed $email,
        mixed $password,
        mixed $passwordConfirm
    ): ValidationResult {
        $errors = [];

        $usernameResult = $this->usernameValidator->validate($username);
        if (!$usernameResult->isValid()) {
            $errors['user_login'] = (string) $usernameResult->getError();
        }

        $emailResult = $this->emailValidator->validate($email);
        if (!$emailResult->isValid()) {
            $errors['user_mail'] = (string) $emailResult->getError();
        }

        $passwordResult = $this->passwordValidator->validate($password);
        if (!$passwordResult->isValid()) {
            $errors['user_password'] = (string) $passwordResult->getError();
        }

        $confirmResult = $this->confirmPasswordValidator->validate($password, $passwordConfirm);
        if (!$confirmResult->isValid()) {
            $errors['password_confirm'] = (string) $confirmResult->getError();
        }

        if ($errors !== []) {
            return new ValidationResult($errors);
        }

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

        $typeResult = $this->productCategoryValidator->validate($type);
        if (!$typeResult->isValid()) {
            $errors['type_p'] = (string) $typeResult->getError();
        }

        $designationResult = $this->productDesignationValidator->validate($designation);
        if (!$designationResult->isValid()) {
            $errors['designation_p'] = (string) $designationResult->getError();
        }

        $priceResult = $this->productPriceValidator->validate($priceHt);
        if (!$priceResult->isValid()) {
            $errors['prix_ht'] = (string) $priceResult->getError();
        }

        $stockResult = $this->productStockValidator->validate($stock);
        if (!$stockResult->isValid()) {
            $errors['stock_p'] = (string) $stockResult->getError();
        }

        $dateResult = $this->productDateInValidator->validate($dateIn);
        if (!$dateResult->isValid()) {
            $errors['date_in'] = (string) $dateResult->getError();
        }

        $promotionResult = $this->productPromotionValidator->validate($promotion);
        if (!$promotionResult->isValid()) {
            $errors['ppromo'] = (string) $promotionResult->getError();
        }

        $imageResult = $this->productImageValidator->validate($image);
        if (!$imageResult->isValid()) {
            $errors['product_image'] = (string) $imageResult->getError();
        }

        if ($errors !== []) {
            return new ValidationResult($errors);
        }

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
