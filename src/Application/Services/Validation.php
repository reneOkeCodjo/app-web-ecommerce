<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Application\DTO\LoginRequestDTO;
use App\Application\DTO\RegisterRequestDTO;
use App\Application\Validation\ConfirmPasswordValidator;
use App\Application\Validation\EmailValidator;
use App\Application\Validation\LoginPasswordValidator;
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
        private ConfirmPasswordValidator $confirmPasswordValidator = new ConfirmPasswordValidator()
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
}
