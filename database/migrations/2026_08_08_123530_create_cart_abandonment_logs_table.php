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
        Schema::create('cart_abandonment_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('cascade');
            $table->string('email', 255)->nullable();
            $table->json('cart_data');
            $table->timestamp('last_activity_at');
            $table->timestamp('notification_sent_at')->nullable();
            $table->boolean('is_recovered')->default(false);
            $table->timestamps();

            $table->index(['last_activity_at', 'notification_sent_at', 'is_recovered'], 'idx_cart_logs_activity_sent');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cart_abandonment_logs');
    }
};
