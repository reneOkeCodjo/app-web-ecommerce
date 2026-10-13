<?php

declare(strict_types=1);

namespace App\Application\Validation\Product;

use App\Application\Validation\Shared\FieldResult;
use App\Application\Validation\Shared\RequiredValidator;
use App\Application\Validation\Shared\ValidatorInterface;
use DateTimeImmutable;

/**
 * Validates product entry date as Y-m-d.
 */
final class DateInValidator
{
    /** @var list<ValidatorInterface> */
    private array $validators;

    public function __construct()
    {
        $this->validators = [
            new RequiredValidator('La date d’entrée est obligatoire.'),
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

        if (!is_string($value)) {
            return new FieldResult('Date invalide.', $value);
        }

        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        if (
            $date === false
            || $date->format('Y-m-d') !== $value
            || $value < '1000-01-01'
        ) {
            return new FieldResult('Date invalide.', $value);
        }

        return new FieldResult(null, $value);
    }
}
