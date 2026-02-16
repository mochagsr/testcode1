<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int|string, string>>
     */
    public function rules(): array
    {
        return [
            'item_category_id' => ['required', 'integer', 'exists:item_categories,id'],
            'code' => ['nullable', 'string', 'max:60', 'unique:products,code'],
            'name' => ['required', 'string', 'max:200'],
            'unit' => ['required', 'string', 'max:30'],
            'stock' => ['nullable', 'integer', 'min:0'],
            'price_agent' => ['nullable', 'numeric', 'min:0'],
            'price_sales' => ['nullable', 'numeric', 'min:0'],
            'price_general' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    /**
     * Get custom error messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'code.unique' => __('ui.product_code_unique_error'),
            'item_category_id.required' => __('ui.category_required'),
            'name.required' => __('ui.product_name_required'),
        ];
    }

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return auth()->check();
    }
}
