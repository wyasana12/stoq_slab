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
        Schema::create('batches', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('batch_code')->unique();

            $table->foreignUlid('product_id')->constrained('products')->onDelete('cascade');
            $table->foreignUlid('supplier_id')->constrained('suppliers')->onDelete('cascade');
            $table->foreignUlid('warehouse_id')->constrained('warehouses')->onDelete('cascade');
            $table->foreignUlid('rack_warehouse_id')->constrained('rack_warehouses')->onDelete('cascade');
            $table->foreignUlid('detail_pre_order_id')->nullable()->constrained('detail_pre_orders')->onDelete('cascade')->nullOnDelete();

            $table->unsignedInteger('quantity_initial');
            $table->unsignedInteger('quantity_current');

            $table->decimal('price', 12, 2);

            $table->timestamp('production_date')->nullable();
            $table->timestamp('expired_date')->nullable();
            $table->timestamp('inbound_date')->nullable();

            $table->string('condition')->nullable();
            $table->enum('status', [
                'AVAILABLE',
                'QUARANTINED',
                'EXPIRED',
                'DEPLETED',
                'BLOCK',
                'LOST',
            ])->default('AVAILABLE');

            $table->string('barcode')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('batches');
    }
};
