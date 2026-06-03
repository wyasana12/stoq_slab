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
        Schema::create('rack_warehouses', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('rack_code')->unique();
            $table->foreignUlid('warehouse_id')->constrained('warehouses')->onDelete('cascade');
            $table->enum('status', ['AVAILABLE', 'FULL', 'INACTIVE', 'MAINTENANCE'])->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rack_warehouses');
    }
};
