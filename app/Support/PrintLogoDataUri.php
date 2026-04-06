<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

final class PrintLogoDataUri
{
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
}
