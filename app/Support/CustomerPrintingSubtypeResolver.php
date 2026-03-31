<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\CustomerPrintingSubtype;
use Illuminate\Validation\ValidationException;

final class CustomerPrintingSubtypeResolver
{
    /**
     * @return array{id: int|null, name: string|null}
     */
    public static function resolve(
        ?int $customerId,
        string $transactionType,
        ?int $subtypeId = null,
        ?string $subtypeName = null
    ): array {
        if ($transactionType !== TransactionType::PRINTING || $customerId === null || $customerId <= 0) {
            return ['id' => null, 'name' => null];
        }

        $normalizedName = CustomerPrintingSubtype::normalizeName($subtypeName);

        if ($subtypeId !== null && $subtypeId > 0) {
            $existing = CustomerPrintingSubtype::query()
                ->whereKey($subtypeId)
                ->where('customer_id', $customerId)
                ->first();

            if ($existing === null) {
                throw ValidationException::withMessages([
                    'customer_printing_subtype_id' => __('txn.invalid_printing_subtype'),
                ]);
            }

            return ['id' => (int) $existing->id, 'name' => (string) $existing->name];
        }

        if ($normalizedName === '') {
            return ['id' => null, 'name' => null];
        }

        $existing = CustomerPrintingSubtype::query()->firstOrCreate(
            [
                'customer_id' => $customerId,
                'normalized_name' => $normalizedName,
            ],
            [
                'name' => trim((string) $subtypeName),
            ]
        );

        return ['id' => (int) $existing->id, 'name' => (string) $existing->name];
    }
}
