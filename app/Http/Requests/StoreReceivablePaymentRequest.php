<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\FluentRule;
use SanderMuller\FluentValidation\HasFluentRules;

class StoreReceivablePaymentRequest extends FormRequest
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
            'payment_date' => FluentRule::date()->required(),
            'customer_address' => FluentRule::string()->nullable()->max(255),
            'amount' => FluentRule::integer()->required()->min(1),
            'payment_description' => FluentRule::string()->required()->max(120),
            'payment_proof_photo' => FluentRule::image()->nullable()->rule('mimes:jpg,jpeg,png,webp')->max(4096),
            'preferred_invoice_id' => FluentRule::integer()->nullable()->exists('sales_invoices', 'id'),
            'return_to' => FluentRule::string()->nullable()->max(500),
            'customer_signature' => FluentRule::string()->required()->max(120),
            'user_signature' => FluentRule::string()->required()->max(120),
            'notes' => FluentRule::string()->nullable(),
        ];
    }
}
