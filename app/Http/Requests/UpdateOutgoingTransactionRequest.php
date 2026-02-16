<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateOutgoingTransactionRequest extends FormRequest
{
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
            'supplier_id' => ['nullable', 'exists:suppliers,id'],
            'transaction_date' => ['nullable', 'date_format:Y-m-d'],
            'semester_period' => ['nullable', 'string', 'max:20'],
            'description' => ['nullable', 'string', 'max:500'],
            'total' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     */
    public function messages(): array
    {
        return [
            'supplier_id.exists' => __('validation.exists', ['attribute' => __('outgoing_transactions.supplier_id')]),
            'transaction_date.date_format' => __('validation.date_format', ['attribute' => __('outgoing_transactions.transaction_date'), 'format' => 'Y-m-d']),
            'semester_period.string' => __('validation.string', ['attribute' => __('outgoing_transactions.semester_period')]),
            'semester_period.max' => __('validation.max.string', ['attribute' => __('outgoing_transactions.semester_period'), 'max' => 20]),
            'description.max' => __('validation.max.string', ['attribute' => __('outgoing_transactions.description'), 'max' => 500]),
            'total.numeric' => __('validation.numeric', ['attribute' => __('outgoing_transactions.total')]),
            'total.min' => __('validation.min.numeric', ['attribute' => __('outgoing_transactions.total'), 'min' => 0]),
        ];
    }
}
