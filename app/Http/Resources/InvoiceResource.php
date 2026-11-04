<?php

namespace App\Http\Resources;

use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Invoice */
class InvoiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'order_id' => $this->order_id,
            'order' => $this->whenLoaded('order', fn () => [
                'id' => $this->order->id,
                'number' => $this->order->number,
                'ordered_at' => $this->order->ordered_at->toDateString(),
                'total' => (float) $this->order->total,
                'items' => $this->order->relationLoaded('items')
                    ? $this->order->items->map(fn ($item) => [
                        'product_name' => $item->product_name,
                        'quantity' => $item->quantity,
                        'unit_price' => (float) $item->unit_price,
                        'line_total' => (float) $item->line_total,
                    ])
                    : null,
            ]),
            'customer' => $this->whenLoaded('customer', fn () => [
                'id' => $this->customer->id,
                'name' => $this->customer->name,
                'email' => $this->customer->email,
                'phone' => $this->customer->phone,
                'city' => $this->customer->city,
            ]),
            'status' => $this->status->value,
            'amount' => (float) $this->amount,
            'issued_at' => $this->issued_at->toDateString(),
            'due_at' => $this->due_at->toDateString(),
            'notes' => $this->notes,
        ];
    }
}
