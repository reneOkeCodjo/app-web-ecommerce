<?php

declare(strict_types=1);

namespace App\Infrastructure\Storage;

use InvalidArgumentException;
use RuntimeException;

/**
 * Stores an uploaded product image under public/uploads/products/.
 *
 * TEMPORARY (fil rouge / local only): files live in the web root, so they are
 * publicly reachable (including by the developer via the browser). Prefer a
 * private storage + signed URLs or object storage before production.
 */
final class ProductImageUploader
{
    private const MAX_BYTES = 2_097_152; // 2 Mo

    /** @var list<string> */
    private const ALLOWED_MIME = [
        'image/jpeg',
        'image/png',
        'image/webp',
    ];

    /** @var array<string, string> */
    private const MIME_EXTENSION = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    public function __construct(
        private string $absoluteUploadDir,
        private string $publicPathPrefix = '/uploads/products'
    ) {
    }

    /**
     * @param array{name?: string, type?: string, tmp_name?: string, error?: int, size?: int} $file
     * @return string|null Public path (/uploads/products/xxx.ext) or null when no file sent
     */
    public function storeFromUpload(array $file): ?string
    {
        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);

        if ($error === UPLOAD_ERR_NO_FILE) {
            return null;
        }

        if ($error !== UPLOAD_ERR_OK) {
            throw new InvalidArgumentException('Échec de l’upload de l’image.');
        }

        $size = (int) ($file['size'] ?? 0);
        if ($size <= 0 || $size > self::MAX_BYTES) {
            throw new InvalidArgumentException('L’image doit faire au plus 2 Mo.');
        }

        $tmpName = (string) ($file['tmp_name'] ?? '');
        if ($tmpName === '' || !is_uploaded_file($tmpName)) {
            throw new InvalidArgumentException('Fichier upload invalide.');
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($tmpName);

        if (!is_string($mime) || !in_array($mime, self::ALLOWED_MIME, true)) {
            throw new InvalidArgumentException(
                'Format d’image non supporté (JPEG, PNG ou WebP uniquement).'
            );
        }

        $extension = self::MIME_EXTENSION[$mime];
        $filename = bin2hex(random_bytes(16)) . '.' . $extension;

        if (!is_dir($this->absoluteUploadDir) && !mkdir($this->absoluteUploadDir, 0775, true)) {
            throw new RuntimeException('Impossible de créer le dossier uploads.');
        }

        $destination = $this->absoluteUploadDir . DIRECTORY_SEPARATOR . $filename;

        if (!move_uploaded_file($tmpName, $destination)) {
            throw new RuntimeException('Impossible d’enregistrer l’image.');
        }

        return rtrim($this->publicPathPrefix, '/') . '/' . $filename;
    }
}
