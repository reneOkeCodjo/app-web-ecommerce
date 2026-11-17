<?php

// On impose des types stricts pour éviter les conversions silencieuses.
declare(strict_types=1);

// Ce fichier décrit le contrat du service de validation.
namespace App\Application\Services\Shared;

/**
 * Contrat du service qui vérifie les données envoyées par les formulaires.
 */
interface ValidationInterface
{
    /**
     * Valide les champs de connexion.
     */
    public function validateLogin(mixed $username, mixed $password): ValidationResult;

    /**
     * Valide les champs d'inscription.
     */
    public function validateRegister(
        mixed $username,
        mixed $email,
        mixed $password,
        mixed $passwordConfirm
    ): ValidationResult;

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
    ): ValidationResult;
}
