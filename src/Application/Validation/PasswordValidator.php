<?php

declare(strict_types=1);

namespace App\Application\Validation;

/**
 * Validates a password field.
 */
final class PasswordValidator
{
    public const MIN_LENGTH = 8;
    public const MAX_LENGTH = 72;

    /**
     * Uppercase, lowercase, digit, and special character.
     * Length is checked by MinLength / MaxLength validators.
     */
    public const PATTERN = '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z0-9]).+$/';

    public const PATTERN_MESSAGE = 'Password must include uppercase, lowercase, a number, and a special character.';

    /** @var list<ValidatorInterface> */
    private array $validators;

    public function __construct()
    {
        $this->validators = [
            new RequiredValidator('Password is required.'),
            new MinLengthValidator(
                self::MIN_LENGTH,
                'Password must be at least ' . self::MIN_LENGTH . ' characters.'
            ),
            new MaxLengthValidator(
                self::MAX_LENGTH,
                'Password must be at most ' . self::MAX_LENGTH . ' characters.'
            ),
            new RegexValidator(self::PATTERN, self::PATTERN_MESSAGE),
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
