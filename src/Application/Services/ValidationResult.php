<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Application\DTO\LoginRequestDTO;
use App\Application\DTO\RegisterRequestDTO;

/**
 * Outcome of a validation pass: field errors and optional normalized data.
 */
final class ValidationResult
{
    /**
     * @param array<string, string> $errors Field name => message
     */
    public function __construct(
        private array $errors = [],
        private ?LoginRequestDTO $loginRequest = null,
        private ?RegisterRequestDTO $registerRequest = null
    ) {
    }

    /**
     * Whether the validation passed without errors.
     * @return bool True if the validation passed without errors, false otherwise.
     */
    public function isValid(): bool
    {
        return $this->errors === [];
    }

    /**
     * Get the errors.
     * @return array<string, string> The errors.
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Get the first error message.
     * @return string|null The first error message, or null if there are no errors.
     */
    public function getFirstError(): ?string
    {
        if ($this->errors === []) {
            return null;
        }

        return reset($this->errors) ?: null;
    }

    /**
     * Get the error message for a specific field.
     * @param string $field The field name.
     * @return string|null The error message for the field, or null if there is no error.
     */
    public function getError(string $field): ?string
    {
        return $this->errors[$field] ?? null;
    }

    /**
     * Get the login request.
     * @return LoginRequestDTO|null The login request, or null if there is no login request.
     */
    public function getLoginRequest(): ?LoginRequestDTO
    {
        return $this->loginRequest;
    }

    /**
     * Get the register request.
     * @return RegisterRequestDTO|null The register request, or null if there is no register request.
     */
    public function getRegisterRequest(): ?RegisterRequestDTO
    {
        return $this->registerRequest;
    }
}
