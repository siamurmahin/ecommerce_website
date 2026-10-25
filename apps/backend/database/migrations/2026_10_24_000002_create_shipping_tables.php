<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_zones', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('name_en')->nullable();
            $table->unsignedBigInteger('charge')->default(0); // poisha
            $table->unsignedTinyInteger('eta_min_days')->default(1);
            $table->unsignedTinyInteger('eta_max_days')->default(3);
            $table->boolean('is_default')->default(false); // used for districts not assigned to a zone
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('districts', function (Blueprint $table) {
            $table->id();
            $table->string('division');
            $table->string('division_en');
            $table->string('name');
            $table->string('name_en');
            $table->foreignId('delivery_zone_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('districts');
        Schema::dropIfExists('delivery_zones');
    }
};
