<?php

declare(strict_types=1);

namespace App\Application\Validation;

/**
 * Validates and normalizes an email field.
 */
final class EmailValidator
{
    public const MAX_LENGTH = 255;

    /** @var list<ValidatorInterface> */
    private array $validators;

    public function __construct()
    {
        $this->validators = [
            new RequiredValidator('Email is required.'),
            new MaxLengthValidator(
                self::MAX_LENGTH,
                'Email must be at most ' . self::MAX_LENGTH . ' characters.'
            ),
            new RegexValidator(
                '/^[^\s@]+@[^\s@]+\.[^\s@]+$/',
                'Email format is invalid.'
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
