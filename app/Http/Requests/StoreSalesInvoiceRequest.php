<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSalesInvoiceRequest extends FormRequest
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
            'customer_id' => ['required', 'exists:customers,id'],
            'invoice_date' => ['required', 'date_format:Y-m-d'],
            'total' => ['required', 'numeric', 'min:0'],
            'subtotal' => ['nullable', 'numeric', 'min:0'],
            'payment_status' => ['in:unpaid,partial,paid'],
            'semester_period' => ['required', 'string', 'max:20'],
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     */
    public function messages(): array
    {
        return [
            'customer_id.required' => __('validation.required', ['attribute' => __('sales_invoices.customer_id')]),
            'customer_id.exists' => __('validation.exists', ['attribute' => __('sales_invoices.customer_id')]),
            'invoice_date.required' => __('validation.required', ['attribute' => __('sales_invoices.invoice_date')]),
            'invoice_date.date_format' => __('validation.date_format', ['attribute' => __('sales_invoices.invoice_date'), 'format' => 'Y-m-d']),
            'total.required' => __('validation.required', ['attribute' => __('sales_invoices.total')]),
            'total.numeric' => __('validation.numeric', ['attribute' => __('sales_invoices.total')]),
            'total.min' => __('validation.min.numeric', ['attribute' => __('sales_invoices.total'), 'min' => 0]),
            'subtotal.numeric' => __('validation.numeric', ['attribute' => __('sales_invoices.subtotal')]),
            'subtotal.min' => __('validation.min.numeric', ['attribute' => __('sales_invoices.subtotal'), 'min' => 0]),
            'payment_status.in' => __('validation.in', ['attribute' => __('sales_invoices.payment_status')]),
            'semester_period.required' => __('validation.required', ['attribute' => __('sales_invoices.semester_period')]),
            'semester_period.string' => __('validation.string', ['attribute' => __('sales_invoices.semester_period')]),
            'semester_period.max' => __('validation.max.string', ['attribute' => __('sales_invoices.semester_period'), 'max' => 20]),
        ];
    }
}
