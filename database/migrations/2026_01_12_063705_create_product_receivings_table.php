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
        Schema::create('product_receivings', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('receiving_code')->unique();
            $table->ulidMorphs('receivable');
            $table->string('status');
            $table->date('receiving_date')->nullable();
            $table->foreignUlid('receiving_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_receivings');
    }
};
