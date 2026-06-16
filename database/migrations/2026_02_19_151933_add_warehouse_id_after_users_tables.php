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
        Schema::table('users', function (Blueprint $table) {
            $table->string('username')->unique()->after('name');
            $table->string('phone_number')->unique();
            $table->string('region_id')->nullable();
            $table->string('street')->nullable();
            $table->string('postal_code')->nullable();
            $table->date('birth_date')->nullable();
            $table->foreignUlid('warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete()->after('password');
            $table->boolean('is_active')->default(1);
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
