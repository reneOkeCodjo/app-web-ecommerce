<?php

declare(strict_types=1);

namespace App\Application\Validation\Product;

use App\Application\Validation\Shared\FieldResult;
use App\Application\Validation\Shared\RegexValidator;
use App\Application\Validation\Shared\RequiredValidator;
use App\Application\Validation\Shared\ValidatorInterface;

/**
 * Validates a product HT price (DECIMAL(10,2)).
 */
final class PriceValidator
{
    public const PATTERN = '/^\d{1,8}(?:\.\d{1,2})?$/D';

    /** @var list<ValidatorInterface> */
    private array $validators;

    public function __construct()
    {
        $this->validators = [
            new RequiredValidator('Le prix HT est obligatoire.'),
            new RegexValidator(
                self::PATTERN,
                'Prix invalide : utilisez un point et au maximum deux décimales.'
            ),
        ];
    }

    public function validate(mixed $value): FieldResult
    {
        if (is_int($value) || is_float($value)) {
            $value = number_format((float) $value, 2, '.', '');
        }

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
