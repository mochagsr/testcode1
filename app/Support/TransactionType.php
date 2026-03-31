<?php

declare(strict_types=1);

namespace App\Support;

final class TransactionType
{
    public const PRODUCT = 'product';

    public const PRINTING = 'printing';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return [
            self::PRODUCT,
            self::PRINTING,
        ];
    }

    public static function normalize(?string $value): string
    {
        $normalized = strtolower(trim((string) $value));

        return in_array($normalized, self::values(), true)
            ? $normalized
            : self::PRODUCT;
    }
}
