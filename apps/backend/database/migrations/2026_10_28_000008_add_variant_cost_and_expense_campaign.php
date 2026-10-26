<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A variant's own purchase price (poisha); null = use the product's.
        Schema::table('product_variants', function (Blueprint $table) {
            $table->unsignedBigInteger('cost_price')->nullable()->after('compare_price');
        });

        // Ad spend tagged with the utm_campaign it paid for, so the report can show ROAS per campaign.
        Schema::table('expenses', function (Blueprint $table) {
            $table->string('campaign', 150)->nullable()->index()->after('title');
        });
    }

    public function down(): void
    {
        Schema::table('expenses', fn (Blueprint $table) => $table->dropColumn('campaign'));
        Schema::table('product_variants', fn (Blueprint $table) => $table->dropColumn('cost_price'));
    }
};
