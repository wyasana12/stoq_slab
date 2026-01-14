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
        Schema::create('pre_orders', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('PO_code')->unique();
            $table->foreignUlid('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignUlid('supplier_id')->constrained('suppliers')->onDelete('cascade');
            $table->foreignUlid('warehouse_id')->constrained('warehouses')->onDelete('cascade');
            $table->decimal('total_bill', 10, 2);
            $table->enum('status', ['DRAFT', 'PENDING', 'APPROVED', 'COMPLETED', 'CANCELED'])->default('DRAFT');
            $table->timestamp('order_date');
            $table->timestamp('target_order_date')->nullable();
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
        Schema::dropIfExists('pre_orders');
    }
};
