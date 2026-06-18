<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\FluentRule;
use SanderMuller\FluentValidation\HasFluentRules;

class StoreDeliveryNoteRequest extends FormRequest
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
            'order_note_id' => FluentRule::integer()->nullable()->exists('order_notes', 'id'),
            'customer_ship_location_id' => FluentRule::integer()->nullable()->exists('customer_ship_locations', 'id'),
            'transaction_type' => FluentRule::field()->nullable()->rule('in:product,printing'),
            'customer_printing_subtype_id' => FluentRule::integer()->nullable()->exists('customer_printing_subtypes', 'id'),
            'recipient_name' => FluentRule::string()->nullable()->max(150),
            'recipient_phone' => FluentRule::string()->nullable()->max(30),
            'city' => FluentRule::string()->nullable()->max(100),
            'address' => FluentRule::string()->nullable(),
            'notes' => FluentRule::string()->nullable(),
            'items' => FluentRule::array()->required()->min(1),
            'items.*.product_id' => FluentRule::integer()->required()->exists('products', 'id'),
            'items.*.order_note_item_id' => FluentRule::integer()->nullable()->exists('order_note_items', 'id'),
            'items.*.product_code' => FluentRule::string()->nullable()->max(60),
            'items.*.product_name' => FluentRule::string()->required()->max(200),
            'items.*.unit' => FluentRule::string()->nullable()->max(30),
            'items.*.quantity' => FluentRule::integer()->required()->min(1),
            'items.*.notes' => FluentRule::string()->nullable(),
        ];
    }
}
