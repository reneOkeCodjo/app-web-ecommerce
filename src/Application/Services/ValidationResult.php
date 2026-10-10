<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Application\DTO\LoginRequestDTO;

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
        private ?LoginRequestDTO $loginRequest = null
    ) {
    }

    public function isValid(): bool
    {
        return $this->errors === [];
    }

    /**
     * @return array<string, string>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    public function getFirstError(): ?string
    {
        if ($this->errors === []) {
            return null;
        }

        return reset($this->errors) ?: null;
    }

    public function getError(string $field): ?string
    {
        return $this->errors[$field] ?? null;
    }

    public function getLoginRequest(): ?LoginRequestDTO
    {
        return $this->loginRequest;
    }
}
