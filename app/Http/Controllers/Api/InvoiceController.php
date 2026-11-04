<?php

namespace App\Http\Controllers\Api;

use App\Enums\InvoiceStatus;
use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\InvoiceRequest;
use App\Http\Resources\InvoiceResource;
use App\Mail\InvoiceMail;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Order;
use App\Services\InvoicePdf;
use App\Support\ArabicText;
use App\Support\ListQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class InvoiceController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Invoice::class);

        $request->validate(['status' => ['nullable', Rule::enum(InvoiceStatus::class)]]);

        $query = Invoice::query()
            ->with(['customer:id,name,email,phone,city', 'order:id,number,ordered_at,total'])
            ->when($request->input('status'), fn (Builder $q, string $status) => $q->where('status', $status));

        return InvoiceResource::collection(ListQuery::apply(
            $request,
            $query,
            sortable: [
                'number' => 'number',
                'issued_at' => 'issued_at',
                'due_at' => 'due_at',
                'amount' => 'amount',
                'status' => 'status',
                'customer' => fn (Builder $q, string $dir) => $q->orderBy(
                    Customer::select('name')->whereColumn('customers.id', 'invoices.customer_id'),
                    $dir,
                ),
            ],
            defaultSort: 'issued_at',
            search: function (Builder $query, string $term) {
                $query->where(fn (Builder $q) => ListQuery::whereContainsAny($q, ['number'], $term)
                    ->orWhereHas('customer', fn (Builder $c) => ListQuery::whereContainsAny($c, ['search_text'], ArabicText::normalize($term)))
                    ->orWhereHas('order', fn (Builder $o) => ListQuery::whereContainsAny($o, ['number'], $term)));
            },
        ));
    }

    public function store(InvoiceRequest $request): JsonResponse
    {
        $this->authorize('create', Invoice::class);

        $invoice = DB::transaction(function () use ($request) {
            $order = Order::query()->lockForUpdate()->findOrFail($request->validated('order_id'));

            if ($order->status === OrderStatus::Cancelled) {
                throw ValidationException::withMessages([
                    'order_id' => [__('messages.order_cancelled')],
                ]);
            }

            if (Invoice::query()->where('order_id', $order->id)->lockForUpdate()->exists()) {
                Validator::make(
                    ['order_id' => $order->id],
                    ['order_id' => [Rule::unique('invoices', 'order_id')]],
                )->validate();
            }

            return Invoice::create($this->attributes($request, $order));
        }, 3);

        return (new InvoiceResource($this->loaded($invoice)))->response()->setStatusCode(201);
    }

    public function show(Invoice $invoice): InvoiceResource
    {
        $this->authorize('view', $invoice);

        return new InvoiceResource($this->loaded($invoice));
    }

    public function update(InvoiceRequest $request, Invoice $invoice): InvoiceResource|JsonResponse
    {
        $this->authorize('update', $invoice);
        $invoiceId = $invoice->getKey();

        return DB::transaction(function () use ($request, $invoiceId) {
            [$order, $freshInvoice] = $this->lockOrderAndInvoice($invoiceId);

            if ((int) $request->validated('order_id') !== $freshInvoice->order_id) {
                throw ValidationException::withMessages([
                    'order_id' => [__('messages.invoice_order_locked')],
                ]);
            }

            // A paid invoice is a closed document: only its notes may change.
            // Moving it out of "paid" is a separate, admin-only action (reopen).
            if ($freshInvoice->status === InvoiceStatus::Paid) {
                $changed = $request->validated('status') !== InvoiceStatus::Paid->value
                    || $request->validated('issued_at') !== $freshInvoice->issued_at->toDateString()
                    || $request->validated('due_at') !== $freshInvoice->due_at->toDateString();
                if ($changed) {
                    return response()->json(['message' => __('messages.invoice_paid_locked')], 409);
                }

                $freshInvoice->update(['notes' => $request->validated('notes')]);

                return new InvoiceResource($this->loaded($freshInvoice));
            }

            $freshInvoice->update($this->attributes($request, $order));

            return new InvoiceResource($this->loaded($freshInvoice));
        }, 3);
    }

    /** Admin-only: undo "paid" (e.g. a payment that bounced). Draft, or overdue when past due. */
    public function reopen(Invoice $invoice): InvoiceResource|JsonResponse
    {
        $this->authorize('reopen', $invoice);
        $invoiceId = $invoice->getKey();

        return DB::transaction(function () use ($invoiceId) {
            [, $freshInvoice] = $this->lockOrderAndInvoice($invoiceId);

            if ($freshInvoice->status !== InvoiceStatus::Paid) {
                return response()->json(['message' => __('messages.invoice_not_paid')], 409);
            }

            $freshInvoice->update([
                'status' => $freshInvoice->due_at->lt(today()) ? InvoiceStatus::Overdue : InvoiceStatus::Draft,
            ]);

            return new InvoiceResource($this->loaded($freshInvoice));
        }, 3);
    }

    /** Server-rendered PDF (Arabic RTL in Arabic, LTR in English). */
    public function pdf(Invoice $invoice, InvoicePdf $pdf): Response
    {
        $this->authorize('view', $invoice);

        return response($pdf->render($invoice), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$pdf->filename($invoice).'"',
        ]);
    }

    /** Sends the invoice to the customer (MAIL_MAILER=log writes it to the log in this demo). */
    public function email(Invoice $invoice): JsonResponse
    {
        $this->authorize('email', $invoice);

        $invoice->loadMissing('customer');
        Mail::to($invoice->customer->email)->locale(app()->getLocale())->send(new InvoiceMail($invoice));

        return response()->json(['message' => __('messages.invoice_emailed', ['email' => $invoice->customer->email])]);
    }

    /** The email body as HTML, for previewing in the browser. */
    public function emailPreview(Invoice $invoice): Response
    {
        $this->authorize('view', $invoice);

        return response((new InvoiceMail($invoice))->locale(app()->getLocale())->render());
    }

    public function destroy(Invoice $invoice): Response|JsonResponse
    {
        $this->authorize('delete', $invoice);
        $invoiceId = $invoice->getKey();

        return DB::transaction(function () use ($invoiceId) {
            [, $freshInvoice] = $this->lockOrderAndInvoice($invoiceId);

            if ($freshInvoice->status === InvoiceStatus::Paid) {
                return response()->json(['message' => __('messages.invoice_paid_delete')], 409);
            }

            $freshInvoice->delete();

            return response()->noContent();
        }, 3);
    }

    /**
     * Lock the parent order first, then fetch the fresh invoice under lock.
     *
     * @return array{Order, Invoice}
     */
    private function lockOrderAndInvoice(int $invoiceId): array
    {
        $orderId = Invoice::query()->whereKey($invoiceId)->value('order_id');
        if ($orderId === null) {
            throw (new ModelNotFoundException)->setModel(Invoice::class, [$invoiceId]);
        }

        $order = Order::query()->lockForUpdate()->findOrFail($orderId);
        $invoice = Invoice::query()->lockForUpdate()->findOrFail($invoiceId);

        return [$order, $invoice];
    }

    /** Customer and amount always come from the order, never from the client. */
    private function attributes(InvoiceRequest $request, Order $order): array
    {
        return [
            ...$request->validated(),
            'customer_id' => $order->customer_id,
            'amount' => $order->total,
        ];
    }

    private function loaded(Invoice $invoice): Invoice
    {
        return $invoice->load(['customer:id,name,email,phone,city', 'order.items']);
    }
}
