<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Take orders beyond the stock on hand, e.g. before the next shipment arrives.
            $table->boolean('preorder')->default(false)->after('low_stock_threshold');
            $table->string('preorder_note')->nullable()->after('preorder'); // "নভেম্বরের দ্বিতীয় সপ্তাহে ডেলিভারি"
            $table->string('preorder_note_en')->nullable()->after('preorder_note');
        });

        Schema::table('order_items', function (Blueprint $table) {
            // Ordered beyond stock as a pre-order: nothing was taken from stock for it.
            $table->boolean('preorder')->default(false)->after('quantity');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', fn (Blueprint $table) => $table->dropColumn('preorder'));
        Schema::table('products', fn (Blueprint $table) => $table->dropColumn(['preorder', 'preorder_note', 'preorder_note_en']));
    }
};
