<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('payment_wallet')->nullable()->after('payment_status'); // which wallet the money was sent with
            $table->string('payment_sender', 20)->nullable()->after('payment_wallet');
            $table->string('payment_trx_id', 40)->nullable()->index()->after('payment_sender');
            $table->unsignedBigInteger('advance_amount')->default(0)->after('payment_trx_id'); // poisha the customer says they sent
            $table->unsignedBigInteger('paid_amount')->default(0)->after('advance_amount'); // poisha staff confirmed
            $table->timestamp('payment_verified_at')->nullable()->after('paid_amount');
            $table->foreignId('payment_verified_by')->nullable()->after('payment_verified_at')->constrained('users')->nullOnDelete();
            $table->string('payment_note')->nullable()->after('payment_verified_by');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('payment_verified_by');
            $table->dropIndex(['payment_trx_id']);
            $table->dropColumn(['payment_wallet', 'payment_sender', 'payment_trx_id', 'advance_amount', 'paid_amount', 'payment_verified_at', 'payment_note']);
        });
    }
};
