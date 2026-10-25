<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** Customers for orders placed before customer records existed (latest name per phone). */
    public function up(): void
    {
        DB::table('orders')->whereNull('deleted_at')->orderBy('id')->get(['customer_phone', 'customer_name', 'created_at'])
            ->groupBy('customer_phone')
            ->each(function ($orders, string $phone) {
                DB::table('customers')->insertOrIgnore([
                    'phone' => $phone,
                    'name' => $orders->last()->customer_name,
                    'created_at' => $orders->first()->created_at,
                    'updated_at' => now(),
                ]);
            });
    }

    public function down(): void {}
};
