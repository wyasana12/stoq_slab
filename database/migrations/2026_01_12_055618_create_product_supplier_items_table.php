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
        Schema::create('product_supplier_items', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('SPN')->unique();
            $table->foreignUlid('product_id')->constrained('products')->onDelete('cascade');
            $table->foreignUlid('supplier_id')->constrained('suppliers')->onDelete('cascade');
            $table->decimal('unit_price', 12, 2);
            $table->integer('min_order_quantity')->default(1);
            $table->integer('lead_time_days')->nullable();
            $table->integer('return_limit_days')->nullable();
            $table->boolean('is_preferred')->default(false);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['product_id', 'supplier_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_supplier_items');
    }
};
