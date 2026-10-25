<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('thanas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('district_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('name_en')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['district_id', 'name_en']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('thana_id')->nullable()->after('district_name')->constrained()->nullOnDelete();
            $table->string('thana_name')->nullable()->after('thana_id');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('thana_id');
            $table->dropColumn('thana_name');
        });
        Schema::dropIfExists('thanas');
    }
};
