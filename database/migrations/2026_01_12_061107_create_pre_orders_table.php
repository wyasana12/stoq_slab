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
            $table->string('kode_po');
            $table->foreignUlid('user_id')->constrained('User')->onDelete('cascade');
            $table->foreignUlid('supplier_id')->constrained('Supplier')->onDelete('cascade');
            $table->foreignUlid('warehouse_id')->constrained('Warehouse')->onDelete('cascade');
            $table->decimal('total_bill', 10, 2);
            $table->enum('status', ['DRAFT', 'DIKIRIM', 'SELESAI', 'BATAL']);
            $table->timestamp('order_date');
            $table->timestamp('target_order_date')->nullable();
            $table->timestamps();
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
