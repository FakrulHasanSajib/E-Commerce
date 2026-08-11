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
        Schema::create('return_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('reason', 255);
            $table->string('status', 50)->default('requested'); // requested, approved, qc_passed, qc_failed, refunded, rejected
            $table->string('refund_method', 50)->default('wallet'); // wallet, bank
            $table->decimal('refund_amount', 10, 2)->default(0.00);
            $table->text('admin_notes')->nullable();
            $table->text('qc_notes')->nullable();
            $table->timestamps();

            $table->index(['order_id', 'status'], 'idx_returns_order_status');
        });

        Schema::create('return_request_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('return_request_id')->constrained('return_requests')->onDelete('cascade');
            $table->foreignId('order_item_id')->constrained('order_items')->onDelete('cascade');
            $table->unsignedInteger('quantity');
            $table->string('condition', 50)->default('unopened');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('return_request_items');
        Schema::dropIfExists('return_requests');
    }
};
