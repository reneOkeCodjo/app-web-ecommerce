<?php

declare(strict_types=1);

namespace Tests\Unit\Service;

use App\Application\Services\Validation;
use App\Application\Validation\Product\CategoryValidator;
use App\Application\Validation\Product\ImageValidator;
use App\Application\Validation\Product\PriceValidator;
use App\Application\Validation\Product\PromotionValidator;
use App\Application\Validation\Product\StockValidator;
use PHPUnit\Framework\TestCase;

final class ProductValidationTest extends TestCase
{
    private Validation $validation;

    protected function setUp(): void
    {
        $this->validation = new Validation();
    }

    public function testValidProductReturnsNormalizedDto(): void
    {
        $result = $this->validation->validateProduct(
            '  Smartphone  ',
            '  Galaxy A55  ',
            '449.9',
            '10',
            '2026-01-10',
            '15',
            ' /uploads/a.jpg '
        );

        self::assertTrue($result->isValid());
        self::assertNotNull($result->getProductRequest());
        self::assertSame('Smartphone', $result->getProductRequest()->getType());
        self::assertSame('Galaxy A55', $result->getProductRequest()->getDesignation());
        self::assertSame('449.9', $result->getProductRequest()->getPriceHt());
        self::assertSame('10', $result->getProductRequest()->getStock());
        self::assertSame('2026-01-10', $result->getProductRequest()->getDateIn());
        self::assertSame('15', $result->getProductRequest()->getPromotion());
        self::assertSame('/uploads/a.jpg', $result->getProductRequest()->getImage());
    }

    public function testRejectsUnknownCategoryAndInvalidPrice(): void
    {
        $result = $this->validation->validateProduct(
            'Inconnu',
            'Produit',
            '12,99',
            '1',
            '2026-01-10'
        );

        self::assertFalse($result->isValid());
        self::assertSame(
            'Choisissez une catégorie proposée dans la liste.',
            $result->getError('type_p')
        );
        self::assertSame(
            'Prix invalide : utilisez un point et au maximum deux décimales.',
            $result->getError('prix_ht')
        );
    }

    public function testOptionalPromotionAndImageCanBeEmpty(): void
    {
        $result = $this->validation->validateProduct(
            'Tablette',
            'iPad',
            '529.00',
            '',
            '2026-05-18',
            '   ',
            ''
        );

        self::assertTrue($result->isValid());
        self::assertSame('0', $result->getProductRequest()?->getStock());
        self::assertNull($result->getProductRequest()?->getPromotion());
        self::assertNull($result->getProductRequest()?->getImage());
    }

    public function testPromotionCannotExceedOneHundred(): void
    {
        $validator = new PromotionValidator();

        $result = $validator->validate('100.01');

        self::assertFalse($result->isValid());
        self::assertSame(
            'La promotion ne peut pas dépasser 100 %.',
            $result->getError()
        );
    }

    public function testPriceValidatorNormalizesNumericInput(): void
    {
        $validator = new PriceValidator();

        $result = $validator->validate(12.5);

        self::assertTrue($result->isValid());
        self::assertSame('12.50', $result->getValue());
    }

    public function testStockValidatorRejectsNegative(): void
    {
        $validator = new StockValidator();

        $result = $validator->validate('-1');

        self::assertFalse($result->isValid());
    }

    public function testImageValidatorRejectsOversizedPath(): void
    {
        $validator = new ImageValidator();

        $result = $validator->validate(str_repeat('a', ImageValidator::MAX_LENGTH + 1));

        self::assertFalse($result->isValid());
    }

    public function testCategoryValidatorUsesCatalogue(): void
    {
        $validator = new CategoryValidator();

        self::assertTrue($validator->validate('Casque audio')->isValid());
        self::assertFalse($validator->validate('')->isValid());
    }
}
