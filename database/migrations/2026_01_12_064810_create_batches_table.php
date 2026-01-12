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
            $table->string('batch_code');
            $table->foreignUlid('product_id')->constrained('products')->onDelete('cascade');
            $table->foreignUlid('supplier_id')->constrained('suppliers')->onDelete('cascade');
            $table->foreignUlid('warehouse_id')->constrained('warehouses')->onDelete('cascade');
            $table->foreignUlid('rack_warehouse_id')->constrained('rack_warehouses')->onDelete('cascade');
            $table->unsignedInteger('quantity_initial');
            $table->unsignedInteger('quantity_current')->nullable();
            $table->decimal('price', 12, 2);
            $table->timestamp('production_date');
            $table->timestamp('expired_date');
            $table->timestamp('inbound_date');
            $table->string('condition')->nullable();
            $table->enum('status', [
                'AVAILABLE',
                'TRANSFER',
                'DISTRIBUTION',
                'BLOCK',
                'QUARANTINED',
                'RETURN',
                'EXPIRED',
                'DEPLETED',
                ])->default('AVAILABLE');
            $table->string('barcode');
            $table->timestamps();
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
