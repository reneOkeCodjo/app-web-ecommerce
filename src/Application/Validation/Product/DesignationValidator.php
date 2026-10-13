<?php

declare(strict_types=1);

namespace App\Application\Validation\Product;

use App\Application\Validation\Shared\FieldResult;
use App\Application\Validation\Shared\MaxLengthValidator;
use App\Application\Validation\Shared\RequiredValidator;
use App\Application\Validation\Shared\ValidatorInterface;

/**
 * Validates and normalizes a product designation.
 */
final class DesignationValidator
{
    public const MAX_LENGTH = 255;

    /** @var list<ValidatorInterface> */
    private array $validators;

    public function __construct()
    {
        $this->validators = [
            new RequiredValidator('La désignation est obligatoire.'),
            new MaxLengthValidator(
                self::MAX_LENGTH,
                'La désignation doit contenir entre 1 et 255 caractères.'
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
