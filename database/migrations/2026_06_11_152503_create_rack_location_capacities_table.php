<?php

use Illuminate\Database\Migrations\Migration;
//use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Schema::create('rack_location_capacities', function (Blueprint $table) {
        //     $table->ulid('id')->primary();
        //     $table->foreignUlid('location_id')->constrained('rack_locations')->cascadeOnDelete();
        //     $table->foreignUlid('unit_id')->constrained('units')->cascadeOnDelete();
        //     $table->unsignedInteger('capacity')->default(0);
        //     $table->unsignedInteger('used')->default(0);
        //     $table->timestamps();
        // });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rack_location_capacities');
    }
};
