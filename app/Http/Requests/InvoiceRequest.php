<?php

namespace App\Http\Requests;

use App\Enums\InvoiceStatus;
use App\Enums\OrderStatus;
use App\Models\Order;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'order_id' => [
                'required',
                'integer',
                'exists:orders,id',
                Rule::unique('invoices', 'order_id')->ignore($this->route('invoice')),
                function (string $attribute, mixed $value, \Closure $fail) {
                    $invoice = $this->route('invoice');
                    // An invoice belongs to one order for life; re-pointing it would rewrite its amount.
                    if ($invoice && (int) $value !== $invoice->order_id) {
                        $fail(__('messages.invoice_order_locked'));

                        return;
                    }
                    if (! $invoice && Order::whereKey($value)->where('status', OrderStatus::Cancelled)->exists()) {
                        $fail(__('messages.order_cancelled'));
                    }
                },
            ],
            'status' => ['required', Rule::enum(InvoiceStatus::class)],
            'issued_at' => ['required', 'date_format:Y-m-d'],
            'due_at' => ['required', 'date_format:Y-m-d', 'after_or_equal:issued_at'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
