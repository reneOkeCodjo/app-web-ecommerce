<?php

declare(strict_types=1);

namespace Tests\Unit\Storage;

use App\Infrastructure\Storage\ProductImageUploader;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class ProductImageUploaderTest extends TestCase
{
    public function testNoFileReturnsNull(): void
    {
        $uploader = new ProductImageUploader(sys_get_temp_dir());

        $path = $uploader->storeFromUpload(['error' => UPLOAD_ERR_NO_FILE]);

        self::assertNull($path);
    }

    public function testRejectsUploadError(): void
    {
        $uploader = new ProductImageUploader(sys_get_temp_dir());

        $this->expectException(InvalidArgumentException::class);
        $uploader->storeFromUpload(['error' => UPLOAD_ERR_INI_SIZE]);
    }
}
