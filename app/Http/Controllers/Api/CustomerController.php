<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CustomerRequest;
use App\Http\Resources\CustomerResource;
use App\Models\Customer;
use App\Support\ArabicText;
use App\Support\ListQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class CustomerController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Customer::class);

        $customers = ListQuery::apply(
            $request,
            Customer::query()->withCount('orders'),
            sortable: [
                'name' => 'name',
                'email' => 'email',
                'city' => 'city',
                'orders_count' => 'orders_count',
                'created_at' => 'created_at',
            ],
            defaultSort: 'created_at',
            search: function (Builder $query, string $term) {
                ListQuery::whereContainsAny($query, ['search_text'], ArabicText::normalize($term));
            },
        );

        return CustomerResource::collection($customers);
    }

    public function store(CustomerRequest $request): JsonResponse
    {
        $this->authorize('create', Customer::class);

        $customer = Customer::create($request->validated());

        return (new CustomerResource($customer->loadCount('orders')))->response()->setStatusCode(201);
    }

    public function show(Customer $customer): CustomerResource
    {
        $this->authorize('view', $customer);

        return new CustomerResource($customer->loadCount('orders'));
    }

    public function update(CustomerRequest $request, Customer $customer): CustomerResource
    {
        $this->authorize('update', $customer);

        $customer->update($request->validated());

        return new CustomerResource($customer->loadCount('orders'));
    }

    public function destroy(Customer $customer): Response|JsonResponse
    {
        $this->authorize('delete', $customer);

        if ($customer->orders()->exists()) {
            return response()->json(['message' => __('messages.customer_has_orders')], 409);
        }

        $customer->delete();

        return response()->noContent();
    }
}
