<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('landing_pages', function (Blueprint $table) {
            // Ready-made design (LandingTemplates::TEMPLATES): sets the page's look on the storefront.
            $table->string('template', 20)->default('classic')->after('product_id');
        });
    }

    public function down(): void
    {
        Schema::table('landing_pages', function (Blueprint $table) {
            $table->dropColumn('template');
        });
    }
};
