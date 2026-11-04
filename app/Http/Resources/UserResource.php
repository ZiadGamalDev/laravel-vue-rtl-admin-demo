<?php

namespace App\Http\Resources;

use App\Models\Customer;
use App\Models\ErrorEvent;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin User */
class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role->value,
            'created_at' => $this->created_at?->toIso8601String(),
            // Only for the signed-in user: what the UI may show. The API enforces the same policies.
            'can' => $this->when($request->user()?->is($this->resource), fn () => $this->abilities()),
        ];
    }

    /** @return array<string, bool> */
    private function abilities(): array
    {
        $abilities = [];
        foreach (['customers' => Customer::class, 'orders' => Order::class, 'invoices' => Invoice::class] as $name => $model) {
            foreach (['create', 'update', 'delete'] as $action) {
                $abilities["$name.$action"] = $this->resource->can($action, $model);
            }
        }
        $abilities['invoices.email'] = $this->resource->can('email', Invoice::class);
        $abilities['invoices.reopen'] = $this->resource->can('reopen', Invoice::class);
        $abilities['errors.view'] = $this->resource->can('viewAny', ErrorEvent::class);
        $abilities['users.manage'] = $this->resource->can('viewAny', User::class);

        return $abilities;
    }
}
