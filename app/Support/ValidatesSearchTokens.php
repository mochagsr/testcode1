<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\Request;
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
            $clean = preg_replace('/[^\pL\pN]/u', '', $token);
            if ($clean !== '' && mb_strlen($clean) < $minimumTokenLength) {
                return false;
            }
        }

        return true;
    }

    /**
     * Standard empty payload for paginated lookup endpoints.
     *
     * @return array<string, mixed>
     */
    protected function emptyLookupPage(int $page, int $perPage): array
    {
        return [
            'data' => [],
            'current_page' => $page,
            'last_page' => 1,
            'per_page' => $perPage,
            'total' => 0,
        ];
    }

    protected function resolveLookupPerPage(Request $request, int $default = 20, int $max = 25): int
    {
        return min(max((int) $request->integer('per_page', $default), 1), $max);
    }

    protected function resolveLookupPage(Request $request): int
    {
        return max(1, (int) $request->integer('page', 1));
    }
}
