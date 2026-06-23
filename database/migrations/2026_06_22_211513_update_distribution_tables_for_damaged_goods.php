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
        Schema::table('stock_distributions', function (Blueprint $table) {
            $table->timestamp('delivered_at')->nullable()->after('dispatched_at');
        });

        Schema::table('stock_distribution_items', function (Blueprint $table) {
            $table->integer('received_quantity')->default(0)->after('approved_quantity');
            $table->integer('damaged_quantity')->default(0)->after('received_quantity');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stock_distributions', function (Blueprint $table) {
            $table->dropColumn('delivered_at');
        });

        Schema::table('stock_distribution_items', function (Blueprint $table) {
            $table->dropColumn(['received_quantity', 'damaged_quantity']);
        });
    }
};
