<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\DashboardStats;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class DashboardController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d', 'required_with:to'],
            'to' => ['nullable', 'date_format:Y-m-d', 'required_with:from', 'after_or_equal:from',
                // The chart has one point per day; cap the range so a request cannot ask for decades.
                function (string $attribute, mixed $value, \Closure $fail) use ($request) {
                    $from = strtotime((string) $request->input('from'));
                    $to = strtotime((string) $value);
                    if ($from && $to && ($to - $from) / 86400 > 365) {
                        $fail(__('messages.range_too_long'));
                    }
                },
            ],
        ]);

        $stats = isset($validated['from'], $validated['to'])
            ? DashboardStats::between(Carbon::parse($validated['from']), Carbon::parse($validated['to']))
            : DashboardStats::lastDays(30);

        return response()->json(['data' => $stats->toArray()]);
    }
}
