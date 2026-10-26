<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Per-unit costs on top of the purchase price: [{label, amount}] in poisha (wrapping, carrying…).
            $table->json('extra_costs')->nullable()->after('cost_price');
            // Add the shop-wide per-unit costs from খরচ সেটিংস.
            $table->boolean('shared_costs')->default(true)->after('extra_costs');
            // With free delivery: does the shop pay the courier, and how much per order (null = the zone's charge).
            $table->boolean('owner_pays_delivery')->default(true)->after('free_delivery');
            $table->unsignedBigInteger('delivery_cost')->nullable()->after('owner_pays_delivery');
        });

        Schema::table('order_items', function (Blueprint $table) {
            // Per unit, kept from the moment of the order: extra + shared costs, and the item's share of what the shop paid for delivery.
            $table->unsignedBigInteger('unit_extra_cost')->default(0)->after('unit_cost');
            $table->unsignedBigInteger('delivery_cost')->default(0)->after('line_total');
        });

        Schema::table('orders', function (Blueprint $table) {
            // What the shop itself pays the courier for this order (free delivery); 0 when the customer pays it.
            $table->unsignedBigInteger('delivery_cost')->default(0)->after('delivery_charge');
        });
    }

    public function down(): void
    {
        Schema::table('orders', fn (Blueprint $table) => $table->dropColumn('delivery_cost'));
        Schema::table('order_items', fn (Blueprint $table) => $table->dropColumn(['unit_extra_cost', 'delivery_cost']));
        Schema::table('products', fn (Blueprint $table) => $table->dropColumn(['extra_costs', 'shared_costs', 'owner_pays_delivery', 'delivery_cost']));
    }
};
