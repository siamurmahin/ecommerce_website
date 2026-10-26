<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->boolean('gift_wrap')->default(false)->after('customer_note');
            $table->string('gift_note', 300)->nullable()->after('gift_wrap');
            $table->unsignedBigInteger('gift_wrap_charge')->default(0)->after('delivery_charge'); // poisha, part of total
            $table->unsignedBigInteger('gift_wrap_cost')->default(0)->after('delivery_cost'); // what wrapping cost the shop
        });

        // A weekly sale (e.g. every Friday) moves itself a week ahead once its window ends.
        Schema::table('flash_sales', function (Blueprint $table) {
            $table->boolean('repeats_weekly')->default(false)->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('flash_sales', fn (Blueprint $table) => $table->dropColumn('repeats_weekly'));
        Schema::table('orders', fn (Blueprint $table) => $table->dropColumn(['gift_wrap', 'gift_note', 'gift_wrap_charge', 'gift_wrap_cost']));
    }
};
