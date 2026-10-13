<?php

declare(strict_types=1);

namespace App\Application\Validation\Product;

use App\Application\Validation\Shared\FieldResult;
use App\Application\Validation\Shared\MaxLengthValidator;

/**
 * Validates an optional product image path or URL.
 */
final class ImageValidator
{
    public const MAX_LENGTH = 255;

    public function validate(mixed $value): FieldResult
    {
        if ($value === null) {
            return new FieldResult(null, null);
        }

        if (is_string($value)) {
            $value = trim($value);
        }

        if ($value === '' || $value === null) {
            return new FieldResult(null, null);
        }

        if (!is_string($value)) {
            return new FieldResult(
                'Le chemin ou l’URL de l’image est invalide.',
                $value
            );
        }

        $error = (new MaxLengthValidator(
            self::MAX_LENGTH,
            'Le chemin ou l’URL de l’image doit faire au plus 255 caractères.'
        ))->validate($value);

        if ($error !== null) {
            return new FieldResult($error, $value);
        }

        return new FieldResult(null, $value);
    }
}
