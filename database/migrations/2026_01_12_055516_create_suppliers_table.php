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
        Schema::create('suppliers', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name')->unique();
            $table->string('contact_person')->unique();
            $table->string('phone_number')->unique();
            $table->string('email')->unique()->nullable();
            $table->foreignUlid('category_id')->constrained('categories')->cascadeOnDelete();
            $table->string('region_id');
            $table->string('street');
            $table->string('postal_code');
            $table->boolean('status');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('suppliers');
    }
};
