<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_returns', function (Blueprint $table) {
            // The courier's own return pickup request (Steadfast create_return_request), and its last known status.
            $table->string('courier_return_id')->nullable()->after('exchange_order_id');
            $table->string('courier_return_status')->nullable()->after('courier_return_id');
        });
    }

    public function down(): void
    {
        Schema::table('order_returns', fn (Blueprint $table) => $table->dropColumn(['courier_return_id', 'courier_return_status']));
    }
};
