<?php

declare(strict_types=1);

namespace App\Application\Validation\Auth;

use App\Application\Validation\Shared\FieldResult;
use App\Application\Validation\Shared\MaxLengthValidator;
use App\Application\Validation\Shared\RequiredValidator;
use App\Application\Validation\Shared\ValidatorInterface;

/**
 * Validates a password on login: required and max length only.
 * Complexity rules belong to registration, not authentication.
 */
final class LoginPasswordValidator
{
    /** @var list<ValidatorInterface> */
    private array $validators;

    public function __construct()
    {
        $this->validators = [
            new RequiredValidator('Password is required.'),
            new MaxLengthValidator(
                PasswordValidator::MAX_LENGTH,
                'Password must be at most ' . PasswordValidator::MAX_LENGTH . ' characters.'
            ),
        ];
    }

    public function validate(mixed $value): FieldResult
    {
        foreach ($this->validators as $validator) {
            $error = $validator->validate($value);

            if ($error !== null) {
                return new FieldResult($error, $value);
            }
        }

        return new FieldResult(null, $value);
    }
}
