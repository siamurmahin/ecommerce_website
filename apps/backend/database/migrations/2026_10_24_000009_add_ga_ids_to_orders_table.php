<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** GA4 browser ids, so the server-side purchase joins the visitor's session. */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('ga_client_id', 64)->nullable()->after('fbc');
            $table->string('ga_session_id', 32)->nullable()->after('ga_client_id');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['ga_client_id', 'ga_session_id']);
        });
    }
};
