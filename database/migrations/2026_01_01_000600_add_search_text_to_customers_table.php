<?php

use App\Support\ArabicText;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            // Normalised name/email/phone/city for Arabic-aware search (see App\Support\ArabicText).
            $table->text('search_text')->nullable();
        });

        DB::table('customers')->orderBy('id')->each(function ($customer) {
            DB::table('customers')->where('id', $customer->id)->update([
                'search_text' => ArabicText::customerSearchText($customer->name, $customer->email, $customer->phone, $customer->city),
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn('search_text');
        });
    }
};
