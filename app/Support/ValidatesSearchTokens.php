<?php

namespace App\Support;

use Illuminate\Support\Str;

trait ValidatesSearchTokens
{
    protected function hasValidSearchTokens(string $search, int $minimumTokenLength = 3): bool
    {
        $tokens = preg_split('/\s+/', Str::lower(trim($search))) ?: [];

        foreach ($tokens as $token) {
            $clean = preg_replace('/[^a-z0-9]/', '', (string) $token);
            if ($clean !== '' && strlen($clean) < $minimumTokenLength) {
                return false;
            }
        }

        return true;
    }
}
