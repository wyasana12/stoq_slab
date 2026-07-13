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
        Schema::table('rack_warehouses', function (Blueprint $table) {
            if (Schema::hasColumn('rack_warehouses', 'category_id')) {
                // Drop foreign key if it exists
                // Usually it's named rack_warehouses_category_id_foreign
                $table->dropForeign(['category_id']);
                $table->dropColumn('category_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('rack_warehouses', function (Blueprint $table) {
            $table->foreignUlid('category_id')->nullable()->constrained('categories');
        });
    }
};
