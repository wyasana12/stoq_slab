<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_distributions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('distribution_code')->unique();
            $table->foreignUlid('store_id')->nullable()->constrained('stores')->nullOnDelete();
            $table->foreignUlid('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $table->date('dispatched_at')->nullable();
            $table->foreignUlid('requested_by')->constrained('users')->cascadeOnDelete();
            $table->foreignUlid('confirmed_by')->nullable()->constrained('users')->nullOnDelete();

            $table->string('shipped_proof_path')->nullable();
            $table->string('shipped_proof_name')->nullable();
            $table->string('shipped_proof_mime')->nullable();
            $table->integer('shipped_proof_size')->nullable();
            $table->timestamp('shipped_proof_upload_at')->nullable();

            $table->string('completed_proof_path')->nullable();
            $table->string('completed_proof_name')->nullable();
            $table->string('completed_proof_mime')->nullable();
            $table->integer('completed_proof_size')->nullable();
            $table->timestamp('completed_proof_uploaded_at')->nullable();

            $table->string('notes')->nullable();
            $table->string('status');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_distributions');
    }
};
