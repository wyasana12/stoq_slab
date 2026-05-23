<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Enums\MutationStatus;

class StockMutations extends Model
{
    use HasFactory, HasUlids, SoftDeletes;

    protected $guarded = ['id'];

    public $incrementing = false;
    public $keyType = 'string';

    public function batch()
    {
        return $this->belongsTo(Batch::class);
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public static function record(
        string $warehouseId,
        string $batchId,
        int $before,
        int $change,
        MutationStatus $status,
        string $referenceType,
        string $referenceId,
        ?string $notes = null
    ) {
        $after = $before + $change;

        if ($after < 0) {
            throw new \Exception('Stock tidak mencukupi.');
        }

        return self::create([
            'warehouse_id' => $warehouseId,
            'batch_id' => $batchId,
            'change_quantity' => $change,
            'before_quantity' => $before,
            'after_quantity' => $after,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'notes' => $notes,
            'status' => $status->value,
        ]);
    }
}
