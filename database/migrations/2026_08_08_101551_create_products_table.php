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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('brand_id')->nullable()->constrained('brands')->onDelete('set null');
            $table->string('name', 150);
            $table->string('slug', 150)->unique();
            $table->text('description')->nullable();
            $table->text('summary')->nullable();
            $table->string('type', 20); // "simple", "variable", "digital"
            $table->string('status', 20)->default('draft'); // "draft", "published", "scheduled"
            $table->timestamp('published_at')->nullable();

            // Simple & Digital fields (null for variable)
            $table->string('sku', 100)->nullable()->unique();
            $table->decimal('price', 10, 2)->nullable()->default(0.00);
            $table->decimal('sale_price', 10, 2)->nullable();
            $table->decimal('cost_price', 10, 2)->nullable();
            $table->boolean('manage_stock')->default(false);
            $table->integer('stock_quantity')->default(0);
            $table->integer('low_stock_threshold')->nullable();

            // Physical shipping dimensions (null for digital)
            $table->decimal('weight', 8, 2)->nullable();
            $table->decimal('width', 8, 2)->nullable();
            $table->decimal('height', 8, 2)->nullable();
            $table->decimal('length', 8, 2)->nullable();

            $table->boolean('is_active')->default(true);
            $table->decimal('average_rating', 3, 2)->default(0.00);
            $table->integer('review_count')->default(0);
            $table->boolean('has_active_offer')->default(false);
            $table->string('meta_title', 150)->nullable();
            $table->string('meta_description', 255)->nullable();

            $table->softDeletes();
            $table->timestamps();

            // Indexes for fast lookups
            $table->index(['status', 'is_active', 'published_at'], 'idx_products_listing');
            $table->index('brand_id', 'idx_products_brand');
            $table->index('price', 'idx_products_price');
            $table->index('type', 'idx_products_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
