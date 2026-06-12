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
        Schema::create('rack_locations', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('rack_id')->constrained('rack_warehouses')->cascadeOnDelete();
            $table->foreignUlid('batch_id')->nullable()->constrained('batches')->cascadeOnDelete();
            $table->unsignedInteger('level');
            $table->unsignedInteger('bin');
            $table->string('location_code')->unique();
            $table->enum('capacity_unit', ['PCS', 'BOX', 'CARTON', 'PALLET']);
            $table->unsignedInteger('capacity')->default(0);
            $table->unsignedInteger('used')->default(0);
            $table->enum('status', ['AVAILABLE', 'FULL', 'PARTIAL', 'BLOCKED', 'MAINTENANCE'])->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rack_locations');
    }
};
