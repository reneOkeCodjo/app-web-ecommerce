<?php

declare(strict_types=1);

namespace App\Application\Validation;

/**
 * Ensures a string value does not exceed a maximum length.
 */
final class MaxLengthValidator implements ValidatorInterface
{
    public function __construct(
        private int $max,
        private string $message
    ) {
    }

    public function validate(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        if (mb_strlen($value) > $this->max) {
            return $this->message;
        }

        return null;
    }
}
