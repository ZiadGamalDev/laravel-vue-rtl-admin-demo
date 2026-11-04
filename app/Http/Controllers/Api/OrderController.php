<?php

namespace App\Http\Controllers\Api;

use App\Enums\InvoiceStatus;
use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\OrderRequest;
use App\Http\Resources\OrderResource;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Order;
use App\Support\ArabicText;
use App\Support\ListQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class OrderController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Order::class);

        return OrderResource::collection(ListQuery::apply(
            $request,
            $this->filtered($request),
            sortable: [
                'number' => 'number',
                'ordered_at' => 'ordered_at',
                'total' => 'total',
                'status' => 'status',
                'customer' => fn (Builder $q, string $dir) => $q->orderBy(
                    Customer::select('name')->whereColumn('customers.id', 'orders.customer_id'),
                    $dir,
                ),
            ],
            defaultSort: 'ordered_at',
            search: $this->search(...),
        ));
    }

    public function store(OrderRequest $request): JsonResponse
    {
        $this->authorize('create', Order::class);

        $order = DB::transaction(function () use ($request) {
            $order = Order::create($request->safe()->except('items'));
            $order->items()->createMany($request->validated('items'));
            $order->refreshTotal();

            return $order;
        }, 3);

        return (new OrderResource($this->loaded($order)))->response()->setStatusCode(201);
    }

    public function show(Order $order): OrderResource
    {
        $this->authorize('view', $order);

        return new OrderResource($this->loaded($order));
    }

    public function update(OrderRequest $request, Order $order): OrderResource|JsonResponse
    {
        $this->authorize('update', $order);
        $orderId = $order->getKey();

        return DB::transaction(function () use ($request, $orderId) {
            $freshOrder = Order::query()->lockForUpdate()->findOrFail($orderId);
            $freshOrder->load('items');
            $invoice = Invoice::query()->where('order_id', $freshOrder->id)->lockForUpdate()->first();

            // A paid invoice is a closed document: its order's items, customer and date are locked,
            // and the order can only move forward (pending -> processing -> completed), never be cancelled.
            if ($invoice?->status === InvoiceStatus::Paid) {
                if ($this->itemsChanged($freshOrder, $request->validated('items'))
                    || (int) $request->validated('customer_id') !== $freshOrder->customer_id) {
                    return response()->json(['message' => __('messages.order_has_invoice')], 409);
                }
                $status = OrderStatus::from($request->validated('status'));
                if ($request->validated('ordered_at') !== $freshOrder->ordered_at->toDateString()
                    || $status === OrderStatus::Cancelled
                    || $status->rank() < $freshOrder->status->rank()) {
                    return response()->json(['message' => __('messages.order_paid_locked')], 409);
                }
            }

            $freshOrder->update($request->safe()->except('items'));
            $freshOrder->items()->delete();
            $freshOrder->items()->createMany($request->validated('items'));
            $freshOrder->refreshTotal();

            // Unpaid invoices follow the order total.
            if ($invoice && $invoice->status !== InvoiceStatus::Paid) {
                $invoice->update(['amount' => $freshOrder->total, 'customer_id' => $freshOrder->customer_id]);
            }

            return new OrderResource($this->loaded($freshOrder->fresh()));
        }, 3);
    }

    public function destroy(Order $order): Response|JsonResponse
    {
        $this->authorize('delete', $order);
        $orderId = $order->getKey();

        return DB::transaction(function () use ($orderId) {
            $freshOrder = Order::query()->lockForUpdate()->findOrFail($orderId);
            $invoice = Invoice::query()->where('order_id', $freshOrder->id)->lockForUpdate()->first();

            // Deleting the order would cascade to its invoice; never silently drop a paid one.
            if ($invoice?->status === InvoiceStatus::Paid) {
                return response()->json(['message' => __('messages.order_paid_delete')], 409);
            }

            $freshOrder->delete();

            return response()->noContent();
        }, 3);
    }

    /** CSV export of the current filtered list (same filters as index). */
    public function export(Request $request)
    {
        $this->authorize('viewAny', Order::class);

        $orders = $this->filtered($request)
            ->when(trim((string) $request->input('search')), fn (Builder $q, string $term) => $this->search($q, $term))
            ->orderByDesc('ordered_at')
            ->orderByDesc('id');

        return response()->streamDownload(function () use ($orders) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM so spreadsheet apps read Arabic correctly
            fputcsv($out, ['number', 'customer', 'status', 'ordered_at', 'items', 'total'], escape: '');
            foreach ($orders->lazy(200) as $order) {
                fputcsv($out, array_map($this->csvCell(...), [
                    $order->number,
                    $order->customer?->name,
                    $order->status->value,
                    $order->ordered_at->toDateString(),
                    $order->items_count,
                    number_format((float) $order->total, 2, '.', ''),
                ]), escape: '');
            }
            fclose($out);
        }, 'orders-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** @return Builder<Order> */
    private function filtered(Request $request): Builder
    {
        $request->validate([
            'status' => ['nullable', Rule::enum(OrderStatus::class)],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'without_invoice' => ['nullable', 'boolean'],
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $query = Order::query()->with(['customer:id,name', 'invoice:id,order_id,number,status'])->withCount('items');

        $query->when($request->input('status'), fn (Builder $q, string $status) => $q->where('status', $status))
            ->when($request->input('from'), fn (Builder $q, string $from) => $q->where('ordered_at', '>=', $from))
            ->when($request->input('to'), fn (Builder $q, string $to) => $q->where('ordered_at', '<=', $to))
            // Orders that can still be invoiced: no invoice yet and not cancelled.
            ->when($request->boolean('without_invoice'), fn (Builder $q) => $q
                ->doesntHave('invoice')
                ->where('status', '!=', OrderStatus::Cancelled));

        return $query;
    }

    /**
     * Neutralise spreadsheet formulas (CSV injection): a customer named
     * "=HYPERLINK(...)" must not become a live formula when the file is opened.
     */
    private function csvCell(mixed $value): string
    {
        $value = (string) $value;

        return preg_match('/^[=+\-@\t\r]/', $value) && ! is_numeric($value) ? "'".$value : $value;
    }

    /** Match the order number or the customer name. */
    private function search(Builder $query, string $term): void
    {
        $query->where(fn (Builder $q) => ListQuery::whereContainsAny($q, ['number'], $term)
            ->orWhereHas('customer', fn (Builder $c) => ListQuery::whereContainsAny($c, ['search_text'], ArabicText::normalize($term))));
    }

    private function loaded(Order $order): Order
    {
        return $order->load(['customer:id,name', 'items', 'invoice:id,order_id,number,status'])->loadCount('items');
    }

    private function itemsChanged(Order $order, array $items): bool
    {
        $current = $order->items->map(fn ($i) => [$i->product_name, (int) $i->quantity, round((float) $i->unit_price, 2)])->all();
        $incoming = array_map(fn ($i) => [$i['product_name'], (int) $i['quantity'], round((float) $i['unit_price'], 2)], $items);

        return $current !== $incoming;
    }
}
