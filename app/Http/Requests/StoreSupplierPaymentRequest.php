<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\FluentRule;
use SanderMuller\FluentValidation\HasFluentRules;

class StoreSupplierPaymentRequest extends FormRequest
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
            'supplier_id' => FluentRule::integer()->required()->exists('suppliers', 'id'),
            'payment_date' => FluentRule::date()->required(),
            'proof_number' => FluentRule::string()->nullable()->max(80),
            'payment_proof_photo' => FluentRule::image()->nullable()->rule('mimes:jpg,jpeg,png,webp')->max(4096),
            'amount' => FluentRule::integer()->required()->min(1),
            'supplier_signature' => FluentRule::string()->nullable()->max(120),
            'user_signature' => FluentRule::string()->nullable()->max(120),
            'notes' => FluentRule::string()->nullable(),
        ];
    }
}
