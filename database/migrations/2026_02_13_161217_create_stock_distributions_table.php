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
        Schema::create('stock_distributions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('distribution_code')->unique();
            $table->foreignUlid('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $table->string('location');
            $table->string('outlet_name')->nullable();
            $table->string('outlet_address')->nullable();
            $table->string('outlet_phone')->nullable();
            $table->string('outlet_contact')->nullable();
            $table->date('dispatched_at')->nullable();
            $table->foreignUlid('requested_by')->constrained('users')->cascadeOnDelete();
            $table->foreignUlid('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('notes')->nullable();
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
        Schema::dropIfExists('stock_distributions');
    }
};
