<?php

use App\Http\Controllers\Api\InvoiceController;
use Illuminate\Support\Facades\Route;

// Documents opened by plain browser navigation (download links, new tabs, bookmarks)
// use the normal session guard rather than the SPA API.
Route::middleware(['auth', 'throttle:documents'])->group(function () {
    Route::get('/documents/invoices/{invoice}.pdf', [InvoiceController::class, 'pdf'])->name('invoices.pdf');
    Route::get('/documents/invoices/{invoice}/email', [InvoiceController::class, 'emailPreview'])->name('invoices.email-preview');
});

// The Vue SPA handles every other non-API path.
Route::view('/{any?}', 'app')->where('any', '^(?!api|sanctum|up|documents).*$')->name('spa');
