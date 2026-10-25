<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('courier', 30);
            $table->string('consignment_id', 100);
            $table->string('tracking_code', 100)->nullable();
            $table->string('status', 30);
            $table->string('raw_status', 100)->nullable();
            $table->unsignedInteger('cod_amount');
            $table->unsignedInteger('delivery_fee')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->json('payload')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->unique(['courier', 'consignment_id']);
            $table->index(['status', 'created_at']);
        });

        Schema::create('fraud_checks', function (Blueprint $table) {
            $table->id();
            $table->string('phone', 11)->unique();
            $table->string('source', 30);
            $table->unsignedInteger('total');
            $table->unsignedInteger('delivered');
            $table->unsignedInteger('cancelled');
            $table->unsignedTinyInteger('success_ratio')->nullable();
            $table->json('couriers')->nullable();
            $table->timestamp('checked_at');
            $table->timestamps();
        });

        Schema::create('incomplete_orders', function (Blueprint $table) {
            $table->id();
            $table->uuid('draft_id')->unique();
            $table->string('customer_name')->nullable();
            $table->string('customer_phone', 11)->index();
            $table->foreignId('district_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('thana_id')->nullable()->constrained()->nullOnDelete();
            $table->text('address')->nullable();
            $table->text('customer_note')->nullable();
            $table->json('items');
            $table->unsignedInteger('subtotal')->default(0);
            $table->string('status', 20)->default('open')->index();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('staff_note')->nullable();
            $table->timestamp('contacted_at')->nullable();
            $table->json('utm')->nullable();
            $table->string('locale', 5)->nullable();
            $table->string('ip', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('last_activity_at')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incomplete_orders');
        Schema::dropIfExists('fraud_checks');
        Schema::dropIfExists('shipments');
    }
};
