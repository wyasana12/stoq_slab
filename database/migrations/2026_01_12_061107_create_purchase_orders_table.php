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
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('po_code')->unique();
            $table->foreignUlid('supplier_id')->constrained('suppliers')->onDelete('cascade');
            $table->foreignUlid('warehouse_id')->constrained('warehouses')->onDelete('cascade');
            $table->foreignUlid('created_by')->constrained('users')->onDelete('cascade');
            $table->decimal('total_amount', 10, 2);
            $table->string('status');
            $table->timestamp('order_date')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_orders');
    }
};
