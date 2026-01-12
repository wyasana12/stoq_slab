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
        Schema::create('history_mutation_stocks', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('batch_id')->constrained('batches')->onDelete('cascade');
            $table->string('type');
            $table->unsignedInteger('quantity_change');
            $table->foreignUlid('user_id')->constrained('users')->onDelete('cascade');
            $table->timestamp('action_date');
            $table->string('description')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('history_mutation_stocks');
    }
};
