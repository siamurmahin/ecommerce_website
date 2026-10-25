<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('landing_pages', function (Blueprint $table) {
            $table->foreignId('variant_of_id')->nullable()->after('id')->constrained('landing_pages')->cascadeOnDelete();
            $table->unsignedTinyInteger('traffic_weight')->default(50);
            $table->unsignedInteger('views')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('landing_pages', function (Blueprint $table) {
            $table->dropConstrainedForeignId('variant_of_id');
            $table->dropColumn(['traffic_weight', 'views']);
        });
    }
};
