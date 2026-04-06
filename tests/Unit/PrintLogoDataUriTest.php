<?php

namespace Tests\Unit;

use App\Support\PrintLogoDataUri;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class PrintLogoDataUriTest extends TestCase
{
    private string $logoRelativePath = 'company/test-print-logo.png';

    private string $logoAbsolutePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->logoAbsolutePath = storage_path('app/public/' . $this->logoRelativePath);

        File::ensureDirectoryExists(dirname($this->logoAbsolutePath));
        File::put($this->logoAbsolutePath, base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Wn7LwAAAABJRU5ErkJggg=='
        ));
    }

    protected function tearDown(): void
    {
        File::delete($this->logoAbsolutePath);

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
}
