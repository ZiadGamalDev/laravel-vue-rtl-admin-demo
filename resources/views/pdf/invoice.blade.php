@php
    use App\Support\Money;
    $items = $invoice->order?->items ?? collect();
@endphp
<html dir="{{ $rtl ? 'rtl' : 'ltr' }}" lang="{{ app()->getLocale() }}">
<head>
<meta charset="utf-8">
<style>
    body { font-family: xbriyaz, dejavusans; font-size: 11pt; color: #1e293b; }
    .muted { color: #64748b; }
    .brand { color: #0f766e; }
    table { width: 100%; border-collapse: collapse; }
    .head td { vertical-align: top; }
    .title { font-size: 26pt; font-weight: bold; color: #0f766e; }
    .rule { border-bottom: 2px solid #0f766e; margin: 10pt 0 14pt; }
    .meta td { padding: 2pt 0; }
    .items th { background: #f1f5f9; color: #475569; font-size: 9.5pt; padding: 6pt; }
    .items td { border-bottom: 1px solid #e2e8f0; padding: 6pt; }
    .num { text-align: {{ $rtl ? 'left' : 'right' }}; }
    .start { text-align: {{ $rtl ? 'right' : 'left' }}; }
    .ltr { direction: ltr; }
    .total td { font-size: 14pt; font-weight: bold; border-top: 2px solid #1e293b; padding-top: 6pt; }
    .badge { background: #ecfdf5; color: #047857; border: 1px solid #a7f3d0; padding: 2pt 8pt; font-size: 9pt; }
    .footer { text-align: center; color: #64748b; font-size: 9pt; border-top: 1px solid #e2e8f0; padding-top: 8pt; margin-top: 30pt; }
</style>
</head>
<body>
<table class="head">
    <tr>
        <td class="start" style="width: 55%">
            <div style="font-size: 15pt; font-weight: bold;">{{ __('invoice.company') }}</div>
            <div class="muted">{{ __('invoice.company_line') }}</div>
        </td>
        <td class="num" style="width: 45%">
            <div class="title">{{ __('invoice.title') }}</div>
            <div class="ltr" style="font-size: 12pt; font-weight: bold;">{{ $invoice->number }}</div>
            <div style="margin-top: 4pt"><span class="badge">{{ __('invoice.statuses.'.$invoice->status->value) }}</span></div>
        </td>
    </tr>
</table>
<div class="rule"></div>

<table class="head">
    <tr>
        <td class="start" style="width: 55%">
            <div class="muted" style="font-size: 9.5pt; font-weight: bold;">{{ __('invoice.bill_to') }}</div>
            <div style="font-size: 13pt; font-weight: bold;">{{ $invoice->customer?->name }}</div>
            @if ($invoice->customer?->city)<div>{{ $invoice->customer->city }}</div>@endif
            <div class="ltr">{{ $invoice->customer?->email }}</div>
            @if ($invoice->customer?->phone)<div class="ltr">{{ $invoice->customer->phone }}</div>@endif
        </td>
        <td style="width: 45%">
            <table class="meta">
                <tr><td class="muted start">{{ __('invoice.issued_at') }}</td><td class="num">{{ Money::date($invoice->issued_at) }}</td></tr>
                <tr><td class="muted start">{{ __('invoice.due_at') }}</td><td class="num">{{ Money::date($invoice->due_at) }}</td></tr>
                <tr><td class="muted start">{{ __('invoice.order') }}</td><td class="num ltr">{{ $invoice->order?->number }}</td></tr>
            </table>
        </td>
    </tr>
</table>

<table class="items" style="margin-top: 16pt">
    <thead>
        <tr>
            <th class="start" style="width: 6%">#</th>
            <th class="start">{{ __('invoice.product') }}</th>
            <th style="width: 10%; text-align: center">{{ __('invoice.quantity') }}</th>
            <th class="num" style="width: 18%">{{ __('invoice.unit_price') }}</th>
            <th class="num" style="width: 18%">{{ __('invoice.line_total') }}</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($items as $index => $item)
            <tr>
                <td class="start muted">{{ $index + 1 }}</td>
                <td class="start">{{ $item->product_name }}</td>
                <td style="text-align: center">{{ $item->quantity }}</td>
                <td class="num">{{ Money::format($item->unit_price) }}</td>
                <td class="num">{{ Money::format($item->line_total) }}</td>
            </tr>
        @endforeach
    </tbody>
</table>

<table style="margin-top: 12pt">
    <tr>
        <td style="width: 55%"></td>
        <td style="width: 45%">
            <table class="total">
                <tr><td class="start">{{ __('invoice.total') }}</td><td class="num">{{ Money::format($invoice->amount) }}</td></tr>
            </table>
        </td>
    </tr>
</table>

<div class="footer">
    <div style="font-weight: bold; color: #334155;">{{ __('invoice.thanks') }}</div>
    <div>{{ __('invoice.demo_note') }}</div>
</div>
</body>
</html>
