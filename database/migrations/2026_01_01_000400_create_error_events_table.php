<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('error_events', function (Blueprint $table) {
            $table->id();
            $table->string('fingerprint', 40)->unique();
            $table->string('exception_class');
            $table->text('message');
            $table->string('file');
            $table->unsignedInteger('line');
            $table->json('trace')->nullable();
            $table->unsignedInteger('count')->default(1);
            $table->timestamp('first_seen_at');
            $table->timestamp('last_seen_at')->index();
            $table->timestamp('resolved_at')->nullable()->index();
            $table->string('last_method', 10)->nullable();
            $table->string('last_url', 500)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('error_events');
    }
};
