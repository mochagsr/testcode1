<?php

namespace Tests\Unit;

use App\Support\PrintLogoDataUri;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class PrintLogoDataUriTest extends TestCase
{
    private string $logoRelativePath = 'company/test-print-logo.png';

    private string $largeLogoRelativePath = 'company/test-print-logo-large.png';

    private string $logoAbsolutePath;

    private string $largeLogoAbsolutePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->logoAbsolutePath = storage_path('app/public/' . $this->logoRelativePath);
        $this->largeLogoAbsolutePath = storage_path('app/public/' . $this->largeLogoRelativePath);

        File::ensureDirectoryExists(dirname($this->logoAbsolutePath));
        File::put($this->logoAbsolutePath, base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Wn7LwAAAABJRU5ErkJggg=='
        ));

        $canvas = imagecreatetruecolor(1200, 600);
        $background = imagecolorallocate($canvas, 255, 255, 255);
        $accent = imagecolorallocate($canvas, 218, 165, 32);
        imagefill($canvas, 0, 0, $background);
        for ($x = 0; $x < 1200; $x += 24) {
            imageline($canvas, $x, 0, 1200 - $x, 600, $accent);
        }
        imagepng($canvas, $this->largeLogoAbsolutePath, 0);
        imagedestroy($canvas);
    }

    protected function tearDown(): void
    {
        File::delete($this->logoAbsolutePath);
        File::delete($this->largeLogoAbsolutePath);

        parent::tearDown();
    }

    public function test_resolve_returns_data_uri_for_relative_storage_path(): void
    {
        $resolved = PrintLogoDataUri::resolve($this->logoRelativePath);

        $this->assertIsString($resolved);
        $this->assertStringStartsWith('data:image/', $resolved);
    }

    public function test_resolve_returns_data_uri_for_absolute_storage_url_when_file_exists_locally(): void
    {
        $resolved = PrintLogoDataUri::resolve('https://teserpos.mitrasejatiberkah.com/storage/' . $this->logoRelativePath);

        $this->assertIsString($resolved);
        $this->assertStringStartsWith('data:image/', $resolved);
    }

    public function test_resolve_for_print_compresses_large_logo_for_faster_preview(): void
    {
        $resolved = PrintLogoDataUri::resolve($this->largeLogoRelativePath);
        $printResolved = PrintLogoDataUri::resolveForPrint($this->largeLogoRelativePath, true);

        $this->assertIsString($resolved);
        $this->assertIsString($printResolved);
        $this->assertStringStartsWith('data:image/', $printResolved);
        $this->assertLessThan(strlen($resolved), strlen($printResolved));
    }
}
