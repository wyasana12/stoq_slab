<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('stock_distributions', function (Blueprint $table) {
            $table->json('shipped_proofs')->nullable()->after('shipped_proof_name');
            $table->json('completed_proofs')->nullable()->after('completed_proof_name');
        });

        // Migrate existing data
        $distributions = DB::table('stock_distributions')->get();
        foreach ($distributions as $distribution) {
            $updates = [];
            
            if ($distribution->shipped_proof_path) {
                $updates['shipped_proofs'] = json_encode([$distribution->shipped_proof_path]);
            }
            
            if ($distribution->completed_proof_path) {
                $updates['completed_proofs'] = json_encode([$distribution->completed_proof_path]);
            }
            
            if (!empty($updates)) {
                DB::table('stock_distributions')->where('id', $distribution->id)->update($updates);
            }
        }

        Schema::table('stock_distributions', function (Blueprint $table) {
            $table->dropColumn([
                'shipped_proof_path',
                'shipped_proof_name',
                'completed_proof_path',
                'completed_proof_name'
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stock_distributions', function (Blueprint $table) {
            $table->string('shipped_proof_path')->nullable();
            $table->string('shipped_proof_name')->nullable();
            $table->string('completed_proof_path')->nullable();
            $table->string('completed_proof_name')->nullable();
        });

        // Migrate data back
        $distributions = DB::table('stock_distributions')->get();
        foreach ($distributions as $distribution) {
            $updates = [];
            
            if ($distribution->shipped_proofs) {
                $proofs = json_decode($distribution->shipped_proofs, true);
                if (is_array($proofs) && count($proofs) > 0) {
                    $updates['shipped_proof_path'] = $proofs[0];
                    $updates['shipped_proof_name'] = basename($proofs[0]);
                }
            }
            
            if ($distribution->completed_proofs) {
                $proofs = json_decode($distribution->completed_proofs, true);
                if (is_array($proofs) && count($proofs) > 0) {
                    $updates['completed_proof_path'] = $proofs[0];
                    $updates['completed_proof_name'] = basename($proofs[0]);
                }
            }
            
            if (!empty($updates)) {
                DB::table('stock_distributions')->where('id', $distribution->id)->update($updates);
            }
        }

        Schema::table('stock_distributions', function (Blueprint $table) {
            $table->dropColumn(['shipped_proofs', 'completed_proofs']);
        });
    }
};
