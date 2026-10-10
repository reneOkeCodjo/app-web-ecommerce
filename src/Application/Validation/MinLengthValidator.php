<?php

declare(strict_types=1);

namespace App\Application\Validation;

/**
 * Ensures a string value has at least a minimum length.
 */
final class MinLengthValidator implements ValidatorInterface
{
    public function __construct(
        private int $min,
        private string $message
    ) {
    }

    public function validate(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        if (mb_strlen($value) < $this->min) {
            return $this->message;
        }

        return null;
    }
}
