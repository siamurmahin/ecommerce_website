<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // What one unit cost the shop when the order was placed (poisha); null = cost price not set yet.
        Schema::table('order_items', function (Blueprint $table) {
            $table->unsignedBigInteger('unit_cost')->nullable()->after('unit_price');
        });

        // Older orders get today's cost price: the best guess there is.
        DB::table('order_items')->whereNull('unit_cost')->whereNotNull('product_id')->update([
            'unit_cost' => DB::table('products')->select('cost_price')->whereColumn('products.id', 'order_items.product_id')->limit(1),
        ]);

        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->string('category', 30)->index();
            $table->string('title');
            $table->unsignedBigInteger('amount'); // poisha
            $table->date('spent_on')->index();
            $table->text('note')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
        Schema::table('order_items', fn (Blueprint $table) => $table->dropColumn('unit_cost'));
    }
};
