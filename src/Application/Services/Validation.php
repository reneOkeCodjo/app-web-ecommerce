<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Application\DTO\LoginRequestDTO;
use App\Application\Validation\PasswordValidator;
use App\Application\Validation\UsernameValidator;

interface ValidationInterface
{
    /**
     * Validate and normalize a login payload.
     *
     * @param mixed $username Raw POST username
     * @param mixed $password Raw POST password
     */
    public function validateLogin(mixed $username, mixed $password): ValidationResult;
}

/**
 * Orchestrates field validators for request payloads.
 */
class Validation implements ValidationInterface
{
    public function __construct(
        private UsernameValidator $usernameValidator = new UsernameValidator(),
        private PasswordValidator $passwordValidator = new PasswordValidator()
    ) {
    }

    public function validateLogin(mixed $username, mixed $password): ValidationResult
    {
        $errors = [];

        $usernameResult = $this->usernameValidator->validate($username);
        if (!$usernameResult->isValid()) {
            $errors['username'] = (string) $usernameResult->getError();
        }

        $passwordResult = $this->passwordValidator->validate($password);
        if (!$passwordResult->isValid()) {
            $errors['password'] = (string) $passwordResult->getError();
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
}
