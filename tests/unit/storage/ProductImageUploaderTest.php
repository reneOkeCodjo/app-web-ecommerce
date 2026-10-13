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

    public function testDeleteByPublicPathRemovesOwnedFile(): void
    {
        $dir = sys_get_temp_dir() . '/product-upload-test-' . bin2hex(random_bytes(4));
        mkdir($dir, 0775, true);
        $file = $dir . '/sample.jpg';
        file_put_contents($file, 'x');

        $uploader = new ProductImageUploader($dir, '/uploads/products');
        $uploader->deleteByPublicPath('/uploads/products/sample.jpg');

        self::assertFileDoesNotExist($file);
        rmdir($dir);
    }

    public function testDeleteByPublicPathIgnoresForeignPaths(): void
    {
        $dir = sys_get_temp_dir() . '/product-upload-test-' . bin2hex(random_bytes(4));
        mkdir($dir, 0775, true);
        $file = $dir . '/keep.jpg';
        file_put_contents($file, 'x');

        $uploader = new ProductImageUploader($dir, '/uploads/products');
        $uploader->deleteByPublicPath('/etc/passwd');
        $uploader->deleteByPublicPath('/uploads/other/keep.jpg');

        self::assertFileExists($file);
        unlink($file);
        rmdir($dir);
    }
}
