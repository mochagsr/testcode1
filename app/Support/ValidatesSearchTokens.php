<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Str;

trait ValidatesSearchTokens
{
    /**
     * Validate that all search tokens meet the minimum length requirement.
     *
     * @param  string  $search The search string
     * @param  int  $minimumTokenLength The minimum characters per token
     * @return bool True if all tokens are valid
     */
    protected function hasValidSearchTokens(string $search, int $minimumTokenLength = 3): bool
    {
        $tokens = preg_split('/\s+/', Str::lower(trim($search))) ?: [];

        foreach ($tokens as $token) {
            $clean = preg_replace('/[^a-z0-9]/', '', $token);
            if ($clean !== '' && strlen($clean) < $minimumTokenLength) {
                return false;
            }
        }

        return true;
    }
}
