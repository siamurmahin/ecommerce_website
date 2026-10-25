<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Phone is the identity (verified by OTP); orders link by phone, so guest orders show up after sign-up.
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('phone', 11)->unique();
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->boolean('is_blocked')->default(false);
            $table->string('blocked_reason')->nullable();
            $table->string('note', 500)->nullable(); // staff only
            $table->timestamp('last_login_at')->nullable();
            $table->timestamps();
        });

        Schema::create('customer_addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('label', 40)->nullable(); // বাসা, অফিস…
            $table->string('name');
            $table->string('phone', 11);
            $table->foreignId('district_id')->constrained();
            $table->foreignId('thana_id')->nullable()->constrained()->nullOnDelete();
            $table->string('address', 500);
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });

        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->unsignedTinyInteger('rating');
            $table->text('body')->nullable();
            $table->json('images')->nullable();
            $table->string('status', 10)->default('pending')->index();
            $table->string('source', 12)->default('website'); // website, admin (entered from Messenger/FB etc.)
            $table->boolean('is_verified')->default(false); // bought and received it
            $table->text('reply')->nullable();
            $table->timestamp('replied_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->unique(['product_id', 'customer_id']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->decimal('rating_avg', 3, 2)->default(0);
            $table->unsignedInteger('reviews_count')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['rating_avg', 'reviews_count']);
        });
        Schema::dropIfExists('reviews');
        Schema::dropIfExists('customer_addresses');
        Schema::dropIfExists('customers');
    }
};
