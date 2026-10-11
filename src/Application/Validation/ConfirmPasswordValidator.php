<?php

declare(strict_types=1);

namespace App\Application\Validation;

/**
 * Ensures password confirmation matches the password.
 */
final class ConfirmPasswordValidator
{
    public function validate(mixed $password, mixed $confirmation): FieldResult
    {
        if (!is_string($confirmation) || $confirmation === '') {
            return new FieldResult('Password confirmation is required.', $confirmation);
        }

        if (!is_string($password) || $password !== $confirmation) {
            return new FieldResult('Password confirmation does not match.', $confirmation);
        }

        return new FieldResult(null, $confirmation);
    }
}
