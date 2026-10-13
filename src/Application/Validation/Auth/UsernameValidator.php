<?php

declare(strict_types=1);

namespace App\Application\Validation\Auth;

use App\Application\Validation\Shared\FieldResult;
use App\Application\Validation\Shared\MaxLengthValidator;
use App\Application\Validation\Shared\RequiredValidator;
use App\Application\Validation\Shared\ValidatorInterface;

/**
 * Validates and normalizes a username field.
 */
final class UsernameValidator
{
    public const MAX_LENGTH = 255;

    /** @var list<ValidatorInterface> */
    private array $validators;

    public function __construct()
    {
        $this->validators = [
            new RequiredValidator('Username is required.'),
            new MaxLengthValidator(
                self::MAX_LENGTH,
                'Username must be at most ' . self::MAX_LENGTH . ' characters.'
            ),
        ];
    }

    public function validate(mixed $value): FieldResult
    {
        if (is_string($value)) {
            $value = trim($value);
        }

        foreach ($this->validators as $validator) {
            $error = $validator->validate($value);

            if ($error !== null) {
                return new FieldResult($error, $value);
            }
        }

        return new FieldResult(null, $value);
    }
}
