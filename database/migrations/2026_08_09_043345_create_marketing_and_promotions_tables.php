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
        Schema::create('gift_cards', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->unique();
            $table->decimal('initial_value', 10, 2);
            $table->decimal('current_balance', 10, 2);
            $table->string('currency', 10)->default('BDT');
            $table->dateTime('expires_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('purchased_by_id')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
        });

        Schema::create('gift_card_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gift_card_id')->constrained('gift_cards')->onDelete('cascade');
            $table->foreignId('order_id')->nullable()->constrained('orders')->onDelete('set null');
            $table->decimal('amount', 10, 2);
            $table->string('type', 20); // credit, debit
            $table->timestamps();
        });

        Schema::create('affiliates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->onDelete('cascade');
            $table->string('affiliate_code', 50)->unique();
            $table->decimal('commission_rate', 5, 2)->default(5.00); // Percentage e.g. 5.00%
            $table->decimal('earnings_balance', 10, 2)->default(0.00);
            $table->string('status', 50)->default('approved'); // pending, approved, suspended
            $table->timestamps();
        });

        Schema::create('affiliate_referrals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('affiliate_id')->constrained('affiliates')->onDelete('cascade');
            $table->foreignId('order_id')->constrained('orders')->onDelete('cascade');
            $table->decimal('order_total', 10, 2);
            $table->decimal('commission_amount', 10, 2);
            $table->string('status', 50)->default('pending'); // pending, paid, rejected
            $table->timestamps();
        });

        Schema::create('marketing_campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('title', 255);
            $table->string('channel', 50); // email, sms, push
            $table->string('subject', 255)->nullable();
            $table->text('message');
            $table->string('status', 50)->default('draft'); // draft, scheduled, sent
            $table->dateTime('scheduled_at')->nullable();
            $table->unsignedInteger('recipients_count')->default(0);
            $table->timestamps();
        });

        Schema::create('popups', function (Blueprint $table) {
            $table->id();
            $table->string('title', 255);
            $table->string('type', 50); // exit_intent, newsletter, timed
            $table->json('content');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('delay_seconds')->default(0);
            $table->timestamps();
        });

        Schema::create('push_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('cascade');
            $table->text('endpoint');
            $table->json('keys')->nullable();
            $table->timestamps();

            $table->index('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('push_subscriptions');
        Schema::dropIfExists('popups');
        Schema::dropIfExists('marketing_campaigns');
        Schema::dropIfExists('affiliate_referrals');
        Schema::dropIfExists('affiliates');
        Schema::dropIfExists('gift_card_transactions');
        Schema::dropIfExists('gift_cards');
    }
};
