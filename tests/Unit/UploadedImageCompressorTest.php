<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\UploadedImageCompressor;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class UploadedImageCompressorTest extends TestCase
{
    public function test_it_stores_uploaded_images_as_jpeg_with_jpg_extension(): void
    {
        Storage::fake('public');

        $file = UploadedFile::fake()->image('ktp.png', 1200, 800);

        $path = UploadedImageCompressor::storeJpeg($file, 'ktp-test');

        $this->assertStringStartsWith('ktp-test/', $path);
        $this->assertStringEndsWith('.jpg', $path);
        Storage::disk('public')->assertExists($path);

        $mimeType = Storage::disk('public')->mimeType($path);
        $this->assertContains($mimeType, ['image/jpeg', 'image/jpg']);
    }
}
