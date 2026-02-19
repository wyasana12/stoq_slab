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
        Schema::create('stock_mutations', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $table->foreignUlid('batch_id')->constrained('batches')->cascadeOnDelete();
            $table->unsignedInteger('change_quantity');
            $table->unsignedInteger('before_quantity');
            $table->unsignedInteger('after_quantity');
            $table->string('reference_type');
            $table->string('reference_id');
            $table->string('notes')->nullable();
            $table->string('status')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_mutations');
    }
};
