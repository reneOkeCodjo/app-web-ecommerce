<?php

declare(strict_types=1);

namespace App\Application\Validation;

/**
 * Single validation rule applied to one value.
 */
interface ValidatorInterface
{
    /**
     * @return string|null Error message, or null when the value is valid
     */
    public function validate(mixed $value): ?string;
}
