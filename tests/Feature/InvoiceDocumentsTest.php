<?php

use App\Enums\Role;
use App\Mail\InvoiceMail;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Order;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Mail;

function documentInvoice(): Invoice
{
    $customer = Customer::factory()->create(['name' => 'ليلى منصور', 'email' => 'layla@example.com']);
    $order = Order::factory()->for($customer)->withItems([['product_name' => 'قهوة عربية', 'quantity' => 2, 'unit_price' => 18.5]])->create();

    return Invoice::factory()->for($order)->create(['customer_id' => $customer->id, 'status' => 'paid']);
}

it('downloads a one-page PDF with an embedded Arabic font', function () {
    signIn(Role::Viewer);
    $invoice = documentInvoice();

    $response = $this->get("/documents/invoices/{$invoice->id}.pdf")->assertOk();
    $pdf = $response->getContent();

    expect($response->headers->get('content-type'))->toBe('application/pdf')
        ->and($response->headers->get('content-disposition'))->toContain($invoice->number.'.pdf')
        ->and($pdf)->toStartWith('%PDF-')
        ->and($pdf)->toContain('XBRiyaz')          // embedded (subset) font
        ->and($pdf)->toContain('/FontFile2')
        ->and(preg_match_all('#/Type\s*/Page\b#', $pdf))->toBe(1);
});

it('renders the email view right-to-left with the invoice details', function () {
    signIn(Role::Viewer);
    $invoice = documentInvoice();

    $this->get("/documents/invoices/{$invoice->id}/email")
        ->assertOk()
        ->assertSee('dir="rtl"', false)
        ->assertSee('ليلى منصور')
        ->assertSee($invoice->number)
        ->assertSee('37.00 $');
});

it('emails the invoice with the PDF attached (staff and admin only)', function () {
    Mail::fake();
    $invoice = documentInvoice();

    signIn(Role::Viewer);
    $this->postJson("/api/invoices/{$invoice->id}/email")->assertStatus(403);
    Mail::assertNothingSent();

    signIn(Role::Staff);
    $this->postJson("/api/invoices/{$invoice->id}/email")
        ->assertOk()
        ->assertJsonPath('message', 'تم إرسال الفاتورة إلى layla@example.com.');

    Mail::assertSent(InvoiceMail::class, function (InvoiceMail $mail) use ($invoice) {
        $attachments = $mail->attachments();

        return $mail->hasTo('layla@example.com')
            && count($attachments) === 1
            && $attachments[0]->as === $invoice->number.'.pdf';
    });
});

it('writes the full message to the log with the log mail driver', function () {
    $log = storage_path('logs/mail-test-'.uniqid().'.log');
    config([
        'mail.default' => 'log',
        'mail.mailers.log.channel' => 'mailtest',
        'logging.channels.mailtest' => ['driver' => 'single', 'path' => $log],
    ]);
    signIn(Role::Admin);
    $invoice = documentInvoice();

    $this->postJson("/api/invoices/{$invoice->id}/email")->assertOk();

    $written = File::get($log);
    expect($written)->toContain('To: layla@example.com')
        ->toContain('Content-Type: application/pdf')
        ->toContain($invoice->number.'.pdf');
    File::delete($log);
});

it('renders the invoice documents in English when asked with ?lang=en', function () {
    signIn(Role::Viewer);
    $invoice = documentInvoice();

    $this->get("/documents/invoices/{$invoice->id}/email?lang=en")->assertOk()->assertSee('dir="ltr"', false)->assertSee('Hello');
});

it('sends signed-out visitors of a document link to the login page', function () {
    $invoice = documentInvoice();

    $this->get("/documents/invoices/{$invoice->id}.pdf")->assertRedirect('/login');
});

it('serves documents to any signed-in role and 404s unknown invoices', function () {
    $invoice = documentInvoice();
    signIn(Role::Viewer);

    $this->get("/documents/invoices/{$invoice->id}/email")->assertOk();
    $this->get('/documents/invoices/999999.pdf')->assertNotFound();
});
