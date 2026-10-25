<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('incomplete_orders', function (Blueprint $table) {
            $table->string('recovery_token', 40)->nullable()->unique();
            $table->timestamp('recovery_sms_at')->nullable()->index();
            $table->timestamp('recovered_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('incomplete_orders', function (Blueprint $table) {
            $table->dropUnique(['recovery_token']);
            $table->dropIndex(['recovery_sms_at']);
            $table->dropColumn(['recovery_token', 'recovery_sms_at', 'recovered_at']);
        });
    }
};
