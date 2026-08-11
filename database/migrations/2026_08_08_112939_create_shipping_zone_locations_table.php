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
        Schema::create('shipping_zone_locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipping_zone_id')->constrained('shipping_zones')->onDelete('cascade');
            $table->string('country_code', 2)->default('BD');
            $table->string('division', 100)->nullable();
            $table->string('district', 100)->nullable();
            $table->string('upazila', 100)->nullable();
            $table->timestamps();

            // Composite unique constraint
            $table->unique(
                ['shipping_zone_id', 'country_code', 'division', 'district', 'upazila'],
                'uniq_shipping_location'
            );

            // Indexes for fast lookups
            $table->index(['country_code', 'division', 'district', 'upazila'], 'idx_shipping_loc_lookup');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shipping_zone_locations');
    }
};
