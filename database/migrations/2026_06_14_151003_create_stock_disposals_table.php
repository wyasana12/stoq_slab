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
        Schema::create('stock_disposals', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('disposal_code')->unique();
            $table->ulid('batch_id')->index();
            $table->ulid('product_id')->index();
            $table->ulid('warehouse_id')->index();
            $table->integer('requested_quantity')->unsigned();
            $table->integer('approved_quantity')->unsigned()->default(0);
            $table->string('reason')->nullable();
            $table->ulid('requested_by')->index();
            $table->ulid('confirmed_by')->nullable()->index();
            $table->string('damage_proof_path')->nullable();
            $table->string('damage_proof_name')->nullable();
            $table->string('damage_proof_mime')->nullable();
            $table->integer('damage_proof_size')->unsigned()->nullable();
            $table->timestamp('damage_proof_uploaded_at')->nullable();
            $table->string('notes', 1000)->nullable();
            $table->string('status'); // requested, approved, rejected
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_disposals');
    }
};
