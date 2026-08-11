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
        Schema::create('bank_payment_submittals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->onDelete('cascade');
            $table->string('bank_name', 100);
            $table->string('account_number', 50);
            $table->string('transaction_reference', 100);
            $table->decimal('amount', 10, 2);
            $table->foreignId('receipt_media_id')->nullable()->constrained('media')->onDelete('set null');
            $table->string('status', 30)->default('pending'); // pending, approved, rejected
            $table->string('rejection_note', 255)->nullable();
            $table->foreignId('verified_by_user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();

            // Composite index for fast admin filter by status & date
            $table->index(['status', 'created_at'], 'idx_bank_submittals_status_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bank_payment_submittals');
    }
};
