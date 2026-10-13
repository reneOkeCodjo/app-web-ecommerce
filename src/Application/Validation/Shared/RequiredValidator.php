<?php

declare(strict_types=1);

namespace App\Application\Validation\Shared;

/**
 * Ensures a value is a non-empty string.
 */
final class RequiredValidator implements ValidatorInterface
{
    public function __construct(
        private string $message = 'This field is required.'
    ) {
    }

    public function validate(mixed $value): ?string
    {
        if (!is_string($value) || $value === '') {
            return $this->message;
        }

        return null;
    }
}
