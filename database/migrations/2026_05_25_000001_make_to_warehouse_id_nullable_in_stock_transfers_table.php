<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_transfers', function (Blueprint $table) {
            $table->dropForeign(['to_warehouse_id']);
        });

        DB::statement('ALTER TABLE stock_transfers MODIFY to_warehouse_id CHAR(26) NULL');

        Schema::table('stock_transfers', function (Blueprint $table) {
            $table->foreign('to_warehouse_id')->references('id')->on('warehouses')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('stock_transfers', function (Blueprint $table) {
            $table->dropForeign(['to_warehouse_id']);
        });

        DB::statement('ALTER TABLE stock_transfers MODIFY to_warehouse_id CHAR(26) NOT NULL');

        Schema::table('stock_transfers', function (Blueprint $table) {
            $table->foreign('to_warehouse_id')->references('id')->on('warehouses')->cascadeOnDelete();
        });
    }
};
