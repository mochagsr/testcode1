<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\FluentRule;
use SanderMuller\FluentValidation\HasFluentRules;

class StoreCustomerRequest extends FormRequest
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
            'code' => FluentRule::field()->nullable()->rule('unique:customers,code')->rule('max:20'),
            'name' => FluentRule::string()->required()->max(255),
            'city' => FluentRule::string()->required()->max(100),
            'customer_level_id' => FluentRule::field()->nullable()->rule('exists:customer_levels,id'),
            'credit_balance' => FluentRule::numeric()->nullable()->min(0),
            'phone_number' => FluentRule::string()->nullable()->max(20),
            'email' => FluentRule::email()->nullable()->max(255),
            'address' => FluentRule::string()->nullable()->max(500),
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     */
    public function messages(): array
    {
        return [
            'code.unique' => __('validation.unique', ['attribute' => __('customers.code')]),
            'code.max' => __('validation.max.string', ['attribute' => __('customers.code'), 'max' => 20]),
            'name.required' => __('validation.required', ['attribute' => __('customers.name')]),
            'name.string' => __('validation.string', ['attribute' => __('customers.name')]),
            'name.max' => __('validation.max.string', ['attribute' => __('customers.name'), 'max' => 255]),
            'city.required' => __('validation.required', ['attribute' => __('customers.city')]),
            'city.string' => __('validation.string', ['attribute' => __('customers.city')]),
            'city.max' => __('validation.max.string', ['attribute' => __('customers.city'), 'max' => 100]),
            'customer_level_id.exists' => __('validation.exists', ['attribute' => __('customers.customer_level_id')]),
            'credit_balance.numeric' => __('validation.numeric', ['attribute' => __('customers.credit_balance')]),
            'credit_balance.min' => __('validation.min.numeric', ['attribute' => __('customers.credit_balance'), 'min' => 0]),
            'phone_number.max' => __('validation.max.string', ['attribute' => __('customers.phone_number'), 'max' => 20]),
            'email.email' => __('validation.email', ['attribute' => __('customers.email')]),
            'email.max' => __('validation.max.string', ['attribute' => __('customers.email'), 'max' => 255]),
            'address.max' => __('validation.max.string', ['attribute' => __('customers.address'), 'max' => 500]),
        ];
    }
}
