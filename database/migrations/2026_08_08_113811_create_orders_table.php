<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->string('order_number', 50)->unique();
            $table->string('status', 30)->default('pending'); // pending, confirmed, processing, shipped, delivered, cancelled
            $table->decimal('subtotal', 10, 2);
            $table->decimal('discount', 10, 2)->default(0.00);
            $table->decimal('shipping_cost', 10, 2)->default(0.00);
            $table->decimal('tax_cost', 10, 2)->default(0.00);
            $table->decimal('grand_total', 10, 2);
            $table->foreignId('coupon_id')->nullable()->constrained('coupons')->onDelete('set null');
            $table->foreignId('shipping_method_id')->constrained('shipping_methods')->onDelete('restrict');
            $table->string('payment_method', 50); // "cod", "online"
            $table->string('payment_status', 30)->default('unpaid'); // unpaid, paid, refunded, partial
            $table->string('customer_name', 100);
            $table->string('customer_email', 100);
            $table->string('customer_phone', 50);
            $table->text('shipping_address');
            $table->string('division', 100)->nullable();
            $table->string('district', 100)->nullable();
            $table->string('upazila', 100)->nullable();
            $table->timestamps();

            // Composite index for admin filters
            $table->index(['status', 'created_at'], 'idx_orders_status_date');
            // Indexes for fast querying
            $table->index('user_id', 'idx_orders_user');
            $table->index('order_number', 'idx_orders_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
