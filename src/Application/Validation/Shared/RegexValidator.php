<?php

declare(strict_types=1);

namespace App\Application\Validation\Shared;

/**
 * Ensures a string value matches a regular expression.
 */
final class RegexValidator implements ValidatorInterface
{
    public function __construct(
        private string $pattern,
        private string $message
    ) {
    }

    public function validate(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        if (preg_match($this->pattern, $value) !== 1) {
            return $this->message;
        }

        return null;
    }
}
