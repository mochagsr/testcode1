<?php

declare(strict_types=1);

namespace App\Support;

final class PrintTextFormatter
{
    public static function wrapWords(?string $text, int $wordsPerLine = 4): string
    {
        $value = trim((string) $text);

        if ($value === '') {
            return '';
        }

        $normalizedWordsPerLine = max(1, $wordsPerLine);
        $words = preg_split('/\s+/u', $value, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        if ($words === []) {
            return $value;
        }

        $lines = array_map(
            static fn (array $chunk): string => implode(' ', $chunk),
            array_chunk($words, $normalizedWordsPerLine)
        );

        return implode("\n", $lines);
    }
}
