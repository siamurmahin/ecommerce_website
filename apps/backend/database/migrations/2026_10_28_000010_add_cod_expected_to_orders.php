<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // What the courier should have paid, frozen at settlement (the order's due drops to 0 once paid).
        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedBigInteger('cod_expected')->nullable()->after('paid_amount');
        });
    }

    public function down(): void
    {
        Schema::table('orders', fn (Blueprint $table) => $table->dropColumn('cod_expected'));
    }
};
