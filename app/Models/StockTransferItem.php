<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class StockTransferItem extends Model
{
    use HasFactory, HasUlids, SoftDeletes;

    protected $table = 'stock_transfer_items';

    protected $guarded = [
        'id',
    ];

    public $incrementing = false;
    public $keyType = 'string';

    public function transfer(): BelongsTo
    {
        return $this->belongsTo(StockTransfers::class, 'transfer_id');
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class, 'batch_id');
    }
}
