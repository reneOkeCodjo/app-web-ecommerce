<?php

declare(strict_types=1);

namespace App\Application\Validation\Shared;

/**
 * Single validation rule applied to one value.
 */
interface ValidatorInterface
{
    /**
     * Validate the value.
     * @param mixed $value The value to validate.
     * @return string|null The error message, or null when the value is valid.
     */
    public function validate(mixed $value): ?string;
}
