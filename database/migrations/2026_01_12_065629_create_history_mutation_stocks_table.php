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
            $table->unsignedInteger('quantity_before');
            $table->unsignedInteger('quantity_after');

            $table->foreignUlid('user_id')->constrained('users')->onDelete('cascade');

            $table->ulid('reference_id');
            $table->string('reference_type')->nullable();
            
            $table->timestamp('action_date')->useCurrent();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['reference_id', 'reference_type']);
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
