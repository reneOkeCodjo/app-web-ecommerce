<?php

declare(strict_types=1);

namespace App\Application\Validation;

/**
 * Result of validating one field/element.
 */
final class FieldResult
{
    public function __construct(
        private ?string $error,
        private mixed $value
    ) {
    }

    /**
     * Whether the validation passed without errors.
     * @return bool True if the validation passed without errors, false otherwise.
     */
    public function isValid(): bool
    {
        return $this->error === null;
    }

    /**
     * Get the error message.
     * @return string|null The error message, or null if there is no error.
     */
    public function getError(): ?string
    {
        return $this->error;
    }

    /**
     * Get the value.
     * @return mixed The value.
     */
    public function getValue(): mixed
    {
        return $this->value;
    }
}
