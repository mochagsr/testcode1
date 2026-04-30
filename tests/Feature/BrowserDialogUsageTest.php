<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

class BrowserDialogUsageTest extends TestCase
{
    public function test_views_do_not_use_browser_native_dialogs(): void
    {
        $violations = [];

        foreach (File::allFiles(resource_path('views')) as $file) {
            $contents = File::get($file->getPathname());
            if (preg_match('/\b(?:alert|confirm|prompt)\s*\(/', $contents) !== 1) {
                continue;
            }

            $violations[] = str_replace(base_path(DIRECTORY_SEPARATOR), '', $file->getPathname());
        }

        $this->assertSame([], $violations);
    }
}
