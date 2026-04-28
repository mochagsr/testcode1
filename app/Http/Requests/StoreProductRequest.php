<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\FluentRule;
use SanderMuller\FluentValidation\HasFluentRules;

class StoreProductRequest extends FormRequest
{
    use HasFluentRules;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int|string, string>>
     */
    public function rules(): array
    {
        return [
            'item_category_id' => FluentRule::integer()->required()->exists('item_categories', 'id'),
            'code' => FluentRule::string()->nullable()->max(60)->unique('products', 'code'),
            'name' => FluentRule::string()->required()->max(200),
            'unit' => FluentRule::string()->required()->max(30),
            'stock' => FluentRule::integer()->nullable()->min(0),
            'price_agent' => FluentRule::numeric()->nullable()->min(0),
            'price_sales' => FluentRule::numeric()->nullable()->min(0),
            'price_general' => FluentRule::numeric()->nullable()->min(0),
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
     */
    public function authorize(): bool
    {
        return auth()->check();
    }
}
