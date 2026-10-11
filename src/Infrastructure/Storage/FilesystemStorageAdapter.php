<?php

declare(strict_types=1);

namespace App\Infrastructure\Storage;

use RuntimeException;

/**
 * Filesystem storage adapter for managing receipt images, product photos, and attachments.
 */
final class FilesystemStorageAdapter
{
    public function __construct(
        private readonly string $storageDir = ''
    ) {}

    public function getStoragePath(string $subpath = ''): string
    {
        $base = $this->storageDir !== '' ? $this->storageDir : dirname(__DIR__, 3) . '/storage';
        return rtrim($base, '/\\') . ($subpath !== '' ? '/' . ltrim($subpath, '/\\') : '');
    }

    public function storeUpload(array $file, string $subfolder): string
    {
        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            throw new RuntimeException('فایل معتبری برای بارگذاری یافت نشد.');
        }

        $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp', 'pdf'];
        $ext = strtolower(pathinfo((string)$file['name'], PATHINFO_EXTENSION));

        if (!in_array($ext, $allowedExtensions, true)) {
            throw new RuntimeException("فرمت فایل غیرمجاز است (.{$ext}).");
        }

        $targetDir = $this->getStoragePath('uploads/' . trim($subfolder, '/'));
        if (!is_dir($targetDir)) {
            @mkdir($targetDir, 0755, true);
        }

        $filename = bin2hex(random_bytes(16)) . '.' . $ext;
        $targetPath = $targetDir . '/' . $filename;

        if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
            throw new RuntimeException('خطا در ذخیره‌سازی فایل در سرور.');
        }

        return 'uploads/' . trim($subfolder, '/') . '/' . $filename;
    }

    public function delete(string $relativePath): bool
    {
        $fullPath = $this->getStoragePath($relativePath);
        if (file_exists($fullPath)) {
            return unlink($fullPath);
        }
        return false;
    }
}
