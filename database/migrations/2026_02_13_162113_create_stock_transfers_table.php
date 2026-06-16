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
        Schema::create('stock_transfers', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('transfer_code')->unique();
            $table->enum('transfer_type', ['in', 'out']);
            $table->foreignUlid('from_warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
            $table->foreignUlid('to_warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
            $table->foreignUlid('product_id')->constrained('products')->cascadeOnDelete();
            $table->unsignedInteger('requested_quantity');
            $table->unsignedInteger('approved_quantity')->default(0);
            $table->foreignUlid('requested_by')->constrained('users')->cascadeOnDelete();
            $table->foreignUlid('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('notes')->nullable();
            $table->string('reason')->nullable();
            $table->string('status');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_transfers');
    }
};
