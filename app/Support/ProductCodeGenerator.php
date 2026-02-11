<?php

namespace App\Support;

use App\Models\Product;
use Illuminate\Support\Str;

class ProductCodeGenerator
{
    public function normalizeInput(?string $value): string
    {
        return $this->normalizeRequestedCode((string) $value);
    }

    public function resolve(?string $requestedCode, string $name, ?int $ignoreId = null): string
    {
        $requestedCode = $this->normalizeRequestedCode((string) $requestedCode);
        if ($requestedCode !== '') {
            return $requestedCode;
        }

        return $this->generateUniqueFromName($name, $ignoreId);
    }

    public function generateBase(string $name): string
    {
        $rawNormalized = Str::of($name)
            ->ascii()
            ->lower()
            ->replaceMatches('/[^a-z0-9\/\-\s]+/', ' ')
            ->replaceMatches('/\s+/', ' ')
            ->trim()
            ->value();

        $normalized = Str::of($name)
            ->ascii()
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', ' ')
            ->trim()
            ->value();

        if ($normalized === '') {
            return 'item';
        }

        preg_match('/[a-z]+/', $normalized, $subjectMatch);
        $subjectRaw = $subjectMatch[0] ?? 'item';
        $subject = $this->compactSubjectToken($subjectRaw);

        preg_match('/\b(\d+)\b/', $normalized, $numberMatch);
        $level = $numberMatch[1] ?? '';

        preg_match('/\b(?:edisi|ed)\s*(\d+)\b/i', $normalized, $editionMatch);
        $edition = isset($editionMatch[1]) ? 'e'.$editionMatch[1] : '';

        preg_match('/\b(?:semester|smt)\s*(\d+)\b/i', $normalized, $semesterMatch);
        $semester = isset($semesterMatch[1]) ? 's'.$semesterMatch[1] : '';

        $yearSuffix = '';
        if (preg_match('/\b(\d{2}|\d{4})\s*[-\/]\s*(\d{2}|\d{4})\b/', $rawNormalized, $yearMatch) === 1) {
            $yearSuffix = substr($yearMatch[1], -1).substr($yearMatch[2], -1);
        }

        return substr($subject.$level.$edition.$semester.$yearSuffix, 0, 60);
    }

    private function generateUniqueFromName(string $name, ?int $ignoreId = null): string
    {
        $base = $this->generateBase($name);
        $candidate = $base;
        $sequence = 1;

        while ($this->productCodeExists($candidate, $ignoreId)) {
            $candidate = $base.str_pad((string) $sequence, 2, '0', STR_PAD_LEFT);
            $sequence++;
        }

        return $candidate;
    }

    private function compactSubjectToken(string $subjectRaw): string
    {
        $subjectRaw = strtolower($subjectRaw);
        $withoutVowels = preg_replace('/[aeiou]/', '', $subjectRaw) ?? '';
        $token = substr($withoutVowels, 0, 2);

        if ($token === '') {
            $token = substr($subjectRaw, 0, 2);
        }

        return $token !== '' ? $token : 'it';
    }

    private function productCodeExists(string $code, ?int $ignoreId = null): bool
    {
        return Product::query()
            ->when($ignoreId !== null, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->where('code', $code)
            ->exists();
    }

    private function normalizeRequestedCode(string $value): string
    {
        return Str::of($value)
            ->trim()
            ->lower()
            ->replaceMatches('/[^a-z0-9\-]+/', '')
            ->replaceMatches('/-+/', '-')
            ->trim('-')
            ->value();
    }
}
