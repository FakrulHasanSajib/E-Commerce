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
        Schema::table('orders', function (Blueprint $table) {
            $table->string('courier_name', 50)->nullable();
            $table->string('courier_tracking_code', 100)->unique()->nullable();
            $table->foreignId('pickup_point_id')->nullable()->constrained('pickup_points')->onDelete('set null');
            $table->date('delivery_date')->nullable();
            $table->string('delivery_slot_label', 100)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['pickup_point_id']);
            $table->dropColumn(['courier_name', 'courier_tracking_code', 'pickup_point_id', 'delivery_date', 'delivery_slot_label']);
        });
    }
};
