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
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->string('subject_type', 255)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('action', 100);
            $table->text('description')->nullable();
            $table->json('properties')->nullable();
            $table->string('ip_address', 50)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamps();

            $table->index(['subject_type', 'subject_id'], 'idx_logs_subject');
            $table->index(['user_id', 'created_at'], 'idx_logs_user_date');
        });

        Schema::create('blacklists', function (Blueprint $table) {
            $table->id();
            $table->string('type', 20); // phone, ip, email
            $table->string('value', 255);
            $table->text('reason')->nullable();
            $table->foreignId('added_by_id')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();

            $table->unique(['type', 'value']);
        });

        Schema::create('customer_segments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->onDelete('cascade');
            $table->string('segment', 50)->default('new'); // new, frequent_buyer, vip, dormant, high_risk
            $table->decimal('total_spent', 10, 2)->default(0.00);
            $table->unsignedInteger('orders_count')->default(0);
            $table->unsignedInteger('returns_count')->default(0);
            $table->timestamp('last_ordered_at')->nullable();
            $table->timestamps();
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->boolean('is_flagged_fraud')->default(false)->after('status')->index();
            $table->json('fraud_reasons')->nullable()->after('is_flagged_fraud');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['is_flagged_fraud', 'fraud_reasons']);
        });

        Schema::dropIfExists('customer_segments');
        Schema::dropIfExists('blacklists');
        Schema::dropIfExists('activity_logs');
    }
};
