<?php

namespace Tests\Feature;

use Tests\TestCase;

class ProductCategoryAutocompleteTest extends TestCase
{
    public function test_product_category_autocomplete_waits_for_three_characters(): void
    {
        $contents = (string) file_get_contents(resource_path('views/products/partials/form.blade.php'));

        $this->assertStringContainsString('const MIN_CATEGORY_SEARCH_LENGTH = 3;', $contents);
        $this->assertStringContainsString('function canSearchCategory(value)', $contents);
        $this->assertStringContainsString('if (!canSearchCategory(value))', $contents);
        $this->assertStringContainsString('if (!canSearchCategory(categorySearchInput.value))', $contents);
    }
}
