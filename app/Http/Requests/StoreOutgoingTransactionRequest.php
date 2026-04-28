<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\FluentRule;
use SanderMuller\FluentValidation\HasFluentRules;

class StoreOutgoingTransactionRequest extends FormRequest
{
    use HasFluentRules;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'supplier_id' => FluentRule::field()->required()->rule('exists:suppliers,id'),
            'transaction_date' => FluentRule::field()->required()->rule('date_format:Y-m-d'),
            'semester_period' => FluentRule::string()->required()->max(20),
            'description' => FluentRule::string()->nullable()->max(500),
            'total' => FluentRule::numeric()->required()->min(0),
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     */
    public function messages(): array
    {
        return [
            'supplier_id.required' => __('validation.required', ['attribute' => __('outgoing_transactions.supplier_id')]),
            'supplier_id.exists' => __('validation.exists', ['attribute' => __('outgoing_transactions.supplier_id')]),
            'transaction_date.required' => __('validation.required', ['attribute' => __('outgoing_transactions.transaction_date')]),
            'transaction_date.date_format' => __('validation.date_format', ['attribute' => __('outgoing_transactions.transaction_date'), 'format' => 'Y-m-d']),
            'semester_period.required' => __('validation.required', ['attribute' => __('outgoing_transactions.semester_period')]),
            'semester_period.string' => __('validation.string', ['attribute' => __('outgoing_transactions.semester_period')]),
            'semester_period.max' => __('validation.max.string', ['attribute' => __('outgoing_transactions.semester_period'), 'max' => 20]),
            'description.max' => __('validation.max.string', ['attribute' => __('outgoing_transactions.description'), 'max' => 500]),
            'total.required' => __('validation.required', ['attribute' => __('outgoing_transactions.total')]),
            'total.numeric' => __('validation.numeric', ['attribute' => __('outgoing_transactions.total')]),
            'total.min' => __('validation.min.numeric', ['attribute' => __('outgoing_transactions.total'), 'min' => 0]),
        ];
    }
}
