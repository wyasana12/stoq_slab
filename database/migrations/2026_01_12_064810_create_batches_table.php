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
            $table->foreignUlid('warehouse_id')->constrained('warehouses')->onDelete('cascade');
            $table->foreignUlid('supplier_id')->constrained('suppliers')->onDelete('cascade');
            $table->string('rack_location')->nullable();

            $table->date('production_date');
            $table->date('expired_date');

            $table->unsignedInteger('initial_quantity');
            $table->unsignedInteger('current_quantity');

            $table->decimal('price', 12, 2);
            $table->string('condition')->nullable();
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
