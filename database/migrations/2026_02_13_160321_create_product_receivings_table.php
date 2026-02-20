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
        Schema::create('product_receivings', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('receiving_code')->unique();
            $table->foreignUlid('purchase_id')->constrained('purchase_orders')->cascadeOnDelete();
            $table->foreignUlid('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $table->string('status');
            $table->date('receiving_date')->nullable();
            $table->foreignUlid('receiving_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_receivings');
    }
};
