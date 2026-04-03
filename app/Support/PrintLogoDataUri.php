<?php

namespace App\Support;

final class PrintLogoDataUri
{
    public static function resolve(?string $logoPath): ?string
    {
        $normalized = ltrim((string) $logoPath, '/\\');

        if ($normalized === '') {
            return null;
        }

        $storageRelative = str_starts_with($normalized, 'storage/')
            ? substr($normalized, strlen('storage/'))
            : $normalized;

        $candidatePaths = array_values(array_unique([
            public_path('storage/' . $storageRelative),
            public_path($normalized),
            storage_path('app/public/' . $storageRelative),
            storage_path('app/' . $storageRelative),
        ]));

        foreach ($candidatePaths as $candidatePath) {
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

        return null;
    }
}
