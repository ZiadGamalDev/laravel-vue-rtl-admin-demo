<?php

namespace App\Console\Commands;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use Illuminate\Console\Command;

/** Draft invoices past their due date become overdue. Runs daily from the scheduler. */
class MarkOverdueInvoices extends Command
{
    protected $signature = 'invoices:mark-overdue';

    protected $description = 'Mark draft invoices whose due date has passed as overdue';

    public function handle(): int
    {
        $count = Invoice::query()
            ->where('status', InvoiceStatus::Draft)
            ->where('due_at', '<', today()->toDateString())
            ->update(['status' => InvoiceStatus::Overdue, 'updated_at' => now()]);

        $this->components->info("$count invoice(s) marked overdue.");

        return self::SUCCESS;
    }
}
