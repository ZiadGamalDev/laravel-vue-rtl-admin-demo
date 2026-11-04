<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Enums\OrderStatus;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Order;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Numbers for the dashboard.
 *
 * "Order value" = sum of the totals of non-cancelled orders placed in the period
 * (by order date), whether or not they have been invoiced or paid. It is not
 * collected revenue; paid money would be the sum of paid invoices.
 * The period defaults to the last 30 days
 * (today included) and is compared with the period of equal length before it.
 */
class DashboardStats
{
    public function __construct(
        private readonly Carbon $from,
        private readonly Carbon $to,
    ) {}

    public static function lastDays(int $days = 30): self
    {
        return new self(Carbon::today()->subDays($days - 1), Carbon::today());
    }

    public static function between(Carbon $from, Carbon $to): self
    {
        return new self($from->copy()->startOfDay(), $to->copy()->startOfDay());
    }

    public function toArray(): array
    {
        $length = $this->from->diffInDays($this->to) + 1;
        $previousTo = $this->from->copy()->subDay();
        $previousFrom = $previousTo->copy()->subDays($length - 1);

        $orderValue = $this->orderValue($this->from, $this->to);
        $previousOrderValue = $this->orderValue($previousFrom, $previousTo);
        $orders = $this->countedOrders($this->from, $this->to)->count();
        $previousOrders = $this->countedOrders($previousFrom, $previousTo)->count();

        $overdue = Invoice::query()->where('status', InvoiceStatus::Overdue);

        return [
            'period' => [
                'from' => $this->from->toDateString(),
                'to' => $this->to->toDateString(),
                'days' => $length,
            ],
            'kpis' => [
                'order_value' => ['value' => $orderValue, 'change' => $this->change($orderValue, $previousOrderValue)],
                'orders' => ['value' => $orders, 'change' => $this->change($orders, $previousOrders)],
                'customers' => [
                    'value' => Customer::count(),
                    'new' => Customer::query()->whereBetween('created_at', [$this->from, $this->to->copy()->endOfDay()])->count(),
                ],
                'overdue_invoices' => [
                    'value' => (clone $overdue)->count(),
                    'amount' => round((float) (clone $overdue)->sum('amount'), 2),
                ],
            ],
            'chart' => $this->dailyOrderValue(),
            'recent_orders' => Order::query()
                ->with('customer:id,name')
                ->withCount('items')
                ->whereBetween('ordered_at', [$this->from->toDateString(), $this->to->toDateString()])
                ->latest('ordered_at')
                ->latest('id')
                ->limit(6)
                ->get()
                ->map(fn (Order $order) => [
                    'id' => $order->id,
                    'number' => $order->number,
                    'customer' => $order->customer?->name,
                    'status' => $order->status->value,
                    'ordered_at' => $order->ordered_at->toDateString(),
                    'items_count' => $order->items_count,
                    'total' => (float) $order->total,
                ]),
        ];
    }

    /** @return list<array{date: string, order_value: float}> */
    private function dailyOrderValue(): array
    {
        $totals = $this->countedOrders($this->from, $this->to)
            ->selectRaw('ordered_at as day, sum(total) as order_value')
            ->groupBy('ordered_at')
            ->pluck('order_value', 'day');

        $points = [];
        foreach (CarbonPeriod::create($this->from, $this->to) as $day) {
            $key = $day->toDateString();
            $points[] = ['date' => $key, 'order_value' => round((float) ($totals[$key] ?? 0), 2)];
        }

        return $points;
    }

    private function orderValue(Carbon $from, Carbon $to): float
    {
        return round((float) $this->countedOrders($from, $to)->sum('total'), 2);
    }

    /** @return Builder<Order> */
    private function countedOrders(Carbon $from, Carbon $to): Builder
    {
        return Order::query()
            ->where('status', '!=', OrderStatus::Cancelled)
            ->whereBetween('ordered_at', [$from->toDateString(), $to->toDateString()]);
    }

    /** Percentage change, or null when there is nothing to compare with. */
    private function change(float|int $current, float|int $previous): ?float
    {
        if ($previous == 0) {
            return null;
        }

        return round(($current - $previous) / $previous * 100, 1);
    }
}
