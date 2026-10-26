<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // "Tell me when it's back": one row per phone per product/variant until it's notified.
        Schema::create('stock_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('phone', 20);
            $table->timestamp('notified_at')->nullable()->index();
            $table->timestamps();
            $table->index(['product_id', 'product_variant_id', 'phone']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_alerts');
    }
};
