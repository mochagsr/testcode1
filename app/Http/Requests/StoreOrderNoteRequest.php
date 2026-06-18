<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\FluentRule;
use SanderMuller\FluentValidation\HasFluentRules;

class StoreOrderNoteRequest extends FormRequest
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
            'note_date' => FluentRule::date()->required(),
            'customer_id' => FluentRule::integer()->required()->exists('customers', 'id'),
            'customer_name' => FluentRule::string()->required()->max(150),
            'customer_phone' => FluentRule::string()->nullable()->max(30),
            'transaction_type' => FluentRule::field()->nullable()->rule('in:product,printing'),
            'customer_printing_subtype_id' => FluentRule::integer()->nullable()->exists('customer_printing_subtypes', 'id'),
            'address' => FluentRule::string()->nullable(),
            'city' => FluentRule::string()->nullable()->max(100),
            'notes' => FluentRule::string()->nullable(),
            'items' => FluentRule::array()->required()->min(1),
            'items.*.product_id' => FluentRule::integer()->required()->exists('products', 'id'),
            'items.*.product_code' => FluentRule::string()->nullable()->max(60),
            'items.*.product_name' => FluentRule::string()->required()->max(200),
            'items.*.quantity' => FluentRule::integer()->required()->min(1),
            'items.*.notes' => FluentRule::string()->nullable(),
        ];
    }
}
