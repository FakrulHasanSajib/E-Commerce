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
        Schema::create('payment_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->onDelete('cascade');
            $table->string('transaction_number', 50)->unique();
            $table->string('gateway_transaction_id', 100)->unique()->nullable(); // Unique for webhook idempotency
            $table->string('gateway', 30); // sslcommerz, mock_online, wallet, etc.
            $table->decimal('amount', 10, 2);
            $table->string('status', 30)->default('pending'); // pending, success, failed, reversed
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->index('order_id', 'idx_payment_transactions_order');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_transactions');
    }
};
