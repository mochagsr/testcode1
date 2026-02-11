<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $products = DB::table('products')
            ->select(['id', 'name', 'code'])
            ->orderBy('id')
            ->get();

        $reservedCodes = [];

        foreach ($products as $product) {
            $requestedCode = $this->normalizeRequestedCode((string) ($product->code ?? ''));
            $base = $requestedCode !== ''
                ? $requestedCode
                : $this->buildReadableBaseCode((string) ($product->name ?? ''));

            if ($base === '') {
                $base = 'item';
            }

            $candidate = $base;
            $sequence = 1;

            while (in_array($candidate, $reservedCodes, true)) {
                $candidate = $base.str_pad((string) $sequence, 2, '0', STR_PAD_LEFT);
                $sequence++;
            }

            DB::table('products')
                ->where('id', (int) $product->id)
                ->update(['code' => $candidate]);

            $reservedCodes[] = $candidate;
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No-op. Product code normalization is data correction and should not be reverted.
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

    private function buildReadableBaseCode(string $name): string
    {
        $normalized = Str::of($name)
            ->ascii()
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', ' ')
            ->trim()
            ->value();

        if ($normalized === '') {
            return '';
        }

        preg_match('/[a-z]+/', $normalized, $subjectMatch);
        $subjectRaw = $subjectMatch[0] ?? 'item';
        $subject = $this->compactSubjectToken($subjectRaw);

        preg_match('/\b(\d+)\b/', $normalized, $numberMatch);
        $level = $numberMatch[1] ?? '';

        preg_match('/\bedisi\s*(\d+)\b/i', $normalized, $editionMatch);
        $edition = isset($editionMatch[1]) ? 'e'.$editionMatch[1] : '';

        preg_match('/\b(?:semester|smt)\s*(\d+)\b/i', $normalized, $semesterMatch);
        $semester = isset($semesterMatch[1]) ? 's'.$semesterMatch[1] : '';

        $yearSuffix = '';
        if (preg_match('/\b(\d{4})\D+(\d{4})\b/', $normalized, $yearMatch) === 1) {
            $yearSuffix = substr($yearMatch[1], -1).substr($yearMatch[2], -1);
        }

        return substr($subject.$level.$edition.$semester.$yearSuffix, 0, 60);
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
};

