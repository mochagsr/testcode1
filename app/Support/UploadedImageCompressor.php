<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

final class UploadedImageCompressor
{
    public static function storeJpeg(
        UploadedFile $file,
        string $directory,
        string $disk = 'public',
        int $quality = 85
    ): string {
        $contents = file_get_contents($file->getRealPath());
        if ($contents === false) {
            throw new RuntimeException('Unable to read uploaded image.');
        }

        if (! function_exists('imagecreatefromstring')) {
            throw new RuntimeException('GD image extension is required to compress uploads.');
        }

        $source = @imagecreatefromstring($contents);
        if ($source === false) {
            throw new RuntimeException('Unable to decode uploaded image.');
        }

        $source = self::applyExifOrientationIfNeeded($file, $source);

        $width = imagesx($source);
        $height = imagesy($source);

        $canvas = imagecreatetruecolor(max(1, $width), max(1, $height));
        if ($canvas === false) {
            imagedestroy($source);
            throw new RuntimeException('Unable to prepare image canvas.');
        }

        $white = imagecolorallocate($canvas, 255, 255, 255);
        imagefilledrectangle($canvas, 0, 0, max(1, $width), max(1, $height), $white);
        imagecopy($canvas, $source, 0, 0, 0, 0, max(1, $width), max(1, $height));

        ob_start();
        imageinterlace($canvas, true);
        imagejpeg($canvas, null, max(1, min(100, $quality)));
        $binary = ob_get_clean();

        imagedestroy($canvas);
        imagedestroy($source);

        if (! is_string($binary) || $binary === '') {
            throw new RuntimeException('Unable to encode compressed image.');
        }

        $directory = trim($directory, '/');
        $path = ($directory !== '' ? $directory.'/' : '').Str::uuid()->toString().'.jpg';
        Storage::disk($disk)->put($path, $binary);

        return $path;
    }

    /**
     * @param  resource|\GdImage  $image
     * @return resource|\GdImage
     */
    private static function applyExifOrientationIfNeeded(UploadedFile $file, $image)
    {
        if (! function_exists('exif_read_data')) {
            return $image;
        }

        $mimeType = strtolower((string) ($file->getMimeType() ?? ''));
        if (! in_array($mimeType, ['image/jpeg', 'image/jpg'], true)) {
            return $image;
        }

        $exif = @exif_read_data($file->getRealPath());
        $orientation = (int) ($exif['Orientation'] ?? 1);

        return match ($orientation) {
            3 => imagerotate($image, 180, 0),
            6 => imagerotate($image, -90, 0),
            8 => imagerotate($image, 90, 0),
            default => $image,
        };
    }
}
