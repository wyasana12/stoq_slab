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
        Schema::create('products', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('sku')->unique();
            $table->string('name');
            $table->foreignUlid('category_id')->constrained('categories')->onDelete('cascade');
            $table->foreignUlid('unit_id')->constrained('units')->onDelete('cascade');
            $table->integer('min_quantity')->nullable();
            $table->integer('return_limit_days')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
