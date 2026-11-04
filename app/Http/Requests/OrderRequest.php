<?php

namespace App\Http\Requests;

use App\Enums\OrderStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'status' => ['required', Rule::enum(OrderStatus::class)],
            'ordered_at' => ['required', 'date_format:Y-m-d'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1', 'max:20'],
            'items.*.product_name' => ['required', 'string', 'min:2', 'max:120'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:999'],
            'items.*.unit_price' => ['required', 'numeric', 'decimal:0,2', 'min:0.01', 'max:99999'],
        ];
    }
}
