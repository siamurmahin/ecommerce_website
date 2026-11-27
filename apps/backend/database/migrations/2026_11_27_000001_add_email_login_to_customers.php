<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Customers may sign in by email code too; such accounts have no phone until they verify one.
        Schema::table('customers', function (Blueprint $table) {
            $table->string('phone', 11)->nullable()->change();
            $table->timestamp('email_verified_at')->nullable()->after('email');
            $table->index('email');
        });

        // Orders placed while signed in belong to that account even without a verified phone.
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('customer_id')->nullable()->after('customer_email')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('customer_id');
        });
        Schema::table('customers', function (Blueprint $table) {
            $table->dropIndex(['email']);
            $table->dropColumn('email_verified_at');
        });
    }
};
