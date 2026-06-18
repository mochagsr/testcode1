<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\FluentRule;
use SanderMuller\FluentValidation\HasFluentRules;

class StoreSalesReturnRequest extends FormRequest
{
    use HasFluentRules;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'customer_id' => FluentRule::integer()->required()->exists('customers', 'id'),
            'return_date' => FluentRule::date()->required(),
            'semester_period' => FluentRule::string()->nullable()->max(30),
            'transaction_type' => FluentRule::field()->nullable()->rule('in:product,printing'),
            'customer_printing_subtype_id' => FluentRule::integer()->nullable()->exists('customer_printing_subtypes', 'id'),
            'reason' => FluentRule::string()->nullable(),
            'items' => FluentRule::array()->required()->min(1),
            'items.*.product_id' => FluentRule::integer()->required()->exists('products', 'id'),
            'items.*.quantity' => FluentRule::integer()->required()->min(1),
        ];
    }
}
