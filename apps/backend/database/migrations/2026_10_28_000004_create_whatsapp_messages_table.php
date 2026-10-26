<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_messages', function (Blueprint $table) {
            $table->id();
            $table->string('to', 20)->index();
            $table->string('template', 100);
            $table->string('language', 10);
            $table->json('params');
            $table->text('preview'); // the filled-in text, for staff
            $table->string('event', 40)->nullable();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->string('gateway', 30);
            $table->string('status', 10)->default('queued'); // queued, sent, failed
            $table->string('error', 500)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_messages');
    }
};
