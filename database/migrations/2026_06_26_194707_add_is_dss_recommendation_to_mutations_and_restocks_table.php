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
        Schema::table('restocks', function (Blueprint $table) {
            $table->boolean('is_dss_recommendation')->default(false)->after('status');
        });

        Schema::table('stock_mutations', function (Blueprint $table) {
            $table->boolean('is_dss_recommendation')->default(false)->after('status');
        });

        Schema::table('stock_distributions', function (Blueprint $table) {
            $table->boolean('is_dss_recommendation')->default(false)->after('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('restocks', function (Blueprint $table) {
            $table->dropColumn('is_dss_recommendation');
        });

        Schema::table('stock_mutations', function (Blueprint $table) {
            $table->dropColumn('is_dss_recommendation');
        });

        Schema::table('stock_distributions', function (Blueprint $table) {
            $table->dropColumn('is_dss_recommendation');
        });
    }
};
