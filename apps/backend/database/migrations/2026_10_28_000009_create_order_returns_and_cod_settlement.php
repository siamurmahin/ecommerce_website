<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_returns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20); // return | exchange
            $table->string('reason', 30);
            $table->string('status', 20)->index();
            // [{order_item_id, quantity}]
            $table->json('items');
            $table->text('note')->nullable(); // the customer's words
            $table->text('staff_note')->nullable();
            $table->unsignedBigInteger('refund_amount')->default(0); // poisha
            $table->string('refund_method', 30)->nullable();
            // Cost of the pieces put back on the shelf (poisha): no longer a cost of this sale in the profit report.
            $table->unsignedBigInteger('restocked_cost')->default(0);
            $table->foreignId('exchange_order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->string('source', 20)->default('customer');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('received_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });

        // Courier COD reconciliation: what the courier actually paid out for the order, and when.
        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedBigInteger('cod_collected')->nullable()->after('paid_amount');
            $table->unsignedBigInteger('courier_fee')->nullable()->after('cod_collected');
            $table->timestamp('cod_settled_at')->nullable()->index()->after('courier_fee');
            $table->string('cod_settlement_ref', 100)->nullable()->after('cod_settled_at');
        });
    }

    public function down(): void
    {
        Schema::table('orders', fn (Blueprint $table) => $table->dropColumn(['cod_collected', 'courier_fee', 'cod_settled_at', 'cod_settlement_ref']));
        Schema::dropIfExists('order_returns');
    }
};
