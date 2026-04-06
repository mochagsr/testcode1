<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

final class PrintLogoDataUri
{
    private const BROWSER_PRINT_MAX_WIDTH = 180;

    private const BROWSER_PRINT_MAX_HEIGHT = 180;

    private const BROWSER_PRINT_INLINE_FILESIZE_THRESHOLD = 122880; // 120 KB

    private const PDF_PRINT_MAX_WIDTH = 140;

    private const PDF_PRINT_MAX_HEIGHT = 140;

    private const PDF_PRINT_INLINE_FILESIZE_THRESHOLD = 81920; // 80 KB

    public static function resolveForPrint(?string $logoPath, bool $preferPublicUrl = false): ?string
    {
        $rawLogoPath = trim((string) $logoPath);

        if ($rawLogoPath === '') {
            return $preferPublicUrl ? static::publicUrl($logoPath) : null;
        }

        foreach (static::candidatePaths($rawLogoPath) as $candidatePath) {
            if (! is_file($candidatePath) || ! is_readable($candidatePath)) {
                continue;
            }

            $optimizedDataUri = static::optimizedDataUri($candidatePath, $preferPublicUrl);
            if ($optimizedDataUri !== null) {
                return $optimizedDataUri;
            }
        }

        if ($preferPublicUrl) {
            return static::publicUrl($logoPath);
        }

        return static::resolve($logoPath);
    }

    public static function resolve(?string $logoPath): ?string
    {
        $rawLogoPath = trim((string) $logoPath);

        if ($rawLogoPath === '') {
            return null;
        }

        foreach (static::candidatePaths($rawLogoPath) as $candidatePath) {
            if (! is_file($candidatePath) || ! is_readable($candidatePath)) {
                continue;
            }

            $mimeType = function_exists('mime_content_type')
                ? (mime_content_type($candidatePath) ?: 'image/png')
                : 'image/png';

            $contents = file_get_contents($candidatePath);

            if ($contents === false) {
                continue;
            }

            return 'data:' . $mimeType . ';base64,' . base64_encode($contents);
        }

        if (preg_match('#^https?://#i', $rawLogoPath) === 1) {
            return $rawLogoPath;
        }

        return null;
    }

    public static function publicUrl(?string $logoPath): ?string
    {
        $rawLogoPath = trim((string) $logoPath);

        if ($rawLogoPath === '') {
            return null;
        }

        if (preg_match('#^https?://#i', $rawLogoPath) === 1) {
            return $rawLogoPath;
        }

        foreach (array_filter([
            $rawLogoPath,
            static::pathFromUrl($rawLogoPath),
        ]) as $possiblePath) {
            $normalized = ltrim((string) $possiblePath, '/\\');

            if ($normalized === '') {
                continue;
            }

            $storageRelative = str_starts_with($normalized, 'storage/')
                ? substr($normalized, strlen('storage/'))
                : $normalized;

            try {
                if (Storage::disk('public')->exists($storageRelative)) {
                    return '/storage/' . str_replace('\\', '/', ltrim($storageRelative, '/\\'));
                }
            } catch (\Throwable) {
                // Fall through to public path checks.
            }

            if (is_file(public_path($normalized))) {
                return '/' . ltrim(str_replace('\\', '/', $normalized), '/');
            }

            if (is_file(public_path('storage/' . $storageRelative))) {
                return '/storage/' . str_replace('\\', '/', ltrim($storageRelative, '/\\'));
            }
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    private static function candidatePaths(string $rawLogoPath): array
    {
        $paths = [];

        foreach (array_filter([
            $rawLogoPath,
            static::pathFromUrl($rawLogoPath),
        ]) as $possiblePath) {
            $normalized = ltrim((string) $possiblePath, '/\\');

            if ($normalized === '') {
                continue;
            }

            $storageRelative = str_starts_with($normalized, 'storage/')
                ? substr($normalized, strlen('storage/'))
                : $normalized;

            $paths[] = static::publicDiskPath($storageRelative);
            $paths[] = public_path('storage/' . $storageRelative);
            $paths[] = public_path($normalized);
            $paths[] = storage_path('app/public/' . $storageRelative);
            $paths[] = storage_path('app/' . $storageRelative);

            if (static::isAbsoluteFilesystemPath($possiblePath)) {
                $paths[] = $possiblePath;
            }
        }

        return array_values(array_unique(array_filter($paths)));
    }

    private static function publicDiskPath(string $storageRelative): ?string
    {
        try {
            return Storage::disk('public')->path($storageRelative);
        } catch (\Throwable) {
            return null;
        }
    }

    private static function pathFromUrl(string $rawLogoPath): ?string
    {
        if (preg_match('#^https?://#i', $rawLogoPath) !== 1) {
            return null;
        }

        $parsedPath = parse_url($rawLogoPath, PHP_URL_PATH);

        return is_string($parsedPath) ? $parsedPath : null;
    }

    private static function isAbsoluteFilesystemPath(string $path): bool
    {
        return str_starts_with($path, DIRECTORY_SEPARATOR)
            || preg_match('/^[A-Za-z]:[\\\\\\/]/', $path) === 1;
    }

    private static function optimizedDataUri(string $candidatePath, bool $preferPublicUrl): ?string
    {
        $imageInfo = @getimagesize($candidatePath);
        if (! is_array($imageInfo)) {
            return static::fileDataUri($candidatePath);
        }

        $width = (int) ($imageInfo[0] ?? 0);
        $height = (int) ($imageInfo[1] ?? 0);
        $mimeType = (string) ($imageInfo['mime'] ?? 'image/png');
        $fileSize = @filesize($candidatePath) ?: 0;
        $maxWidth = $preferPublicUrl ? self::BROWSER_PRINT_MAX_WIDTH : self::PDF_PRINT_MAX_WIDTH;
        $maxHeight = $preferPublicUrl ? self::BROWSER_PRINT_MAX_HEIGHT : self::PDF_PRINT_MAX_HEIGHT;
        $inlineThreshold = $preferPublicUrl
            ? self::BROWSER_PRINT_INLINE_FILESIZE_THRESHOLD
            : self::PDF_PRINT_INLINE_FILESIZE_THRESHOLD;

        if (
            $width > 0
            && $height > 0
            && $width <= $maxWidth
            && $height <= $maxHeight
            && $fileSize > 0
            && $fileSize <= $inlineThreshold
        ) {
            return static::fileDataUri($candidatePath, $mimeType);
        }

        if (! function_exists('imagecreatefromstring')) {
            return static::fileDataUri($candidatePath, $mimeType);
        }

        $contents = @file_get_contents($candidatePath);
        if ($contents === false) {
            return null;
        }

        $source = @imagecreatefromstring($contents);
        if (! is_object($source) && ! is_resource($source)) {
            return static::fileDataUri($candidatePath, $mimeType);
        }

        $targetWidth = $width;
        $targetHeight = $height;

        if ($width > $maxWidth || $height > $maxHeight) {
            $ratio = min(
                $maxWidth / max(1, $width),
                $maxHeight / max(1, $height)
            );

            $targetWidth = max(1, (int) round($width * $ratio));
            $targetHeight = max(1, (int) round($height * $ratio));
        }

        $canvas = imagecreatetruecolor($targetWidth, $targetHeight);
        if (! $canvas) {
            imagedestroy($source);
            return static::fileDataUri($candidatePath, $mimeType);
        }

        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
        imagefilledrectangle($canvas, 0, 0, $targetWidth, $targetHeight, $transparent);

        imagecopyresampled($canvas, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, max(1, $width), max(1, $height));

        ob_start();
        imagepng($canvas, null, 8);
        $optimizedBinary = ob_get_clean();

        imagedestroy($canvas);
        imagedestroy($source);

        if (! is_string($optimizedBinary) || $optimizedBinary === '') {
            return static::fileDataUri($candidatePath, $mimeType);
        }

        return 'data:image/png;base64,' . base64_encode($optimizedBinary);
    }

    private static function fileDataUri(string $candidatePath, ?string $mimeType = null): ?string
    {
        $contents = @file_get_contents($candidatePath);

        if ($contents === false) {
            return null;
        }

        $resolvedMimeType = $mimeType
            ?: (function_exists('mime_content_type') ? (mime_content_type($candidatePath) ?: 'image/png') : 'image/png');

        return 'data:' . $resolvedMimeType . ';base64,' . base64_encode($contents);
    }
}
