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

    public function isValid(): bool
    {
        return $this->error === null;
    }

    public function getError(): ?string
    {
        return $this->error;
    }

    public function getValue(): mixed
    {
        return $this->value;
    }
}
