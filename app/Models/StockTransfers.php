<?php

namespace App\Models;

use App\Casts\TransferStatusCast;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $id
 * @property string|null $from_warehouse_id
 * @property string|null $to_warehouse_id
 * @property string|null $requested_by
 * @property string|null $confirmed_by
 * @property string|null $status
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Warehouse|null $fromWarehouse
 * @property-read Warehouse|null $toWarehouse
 * @property-read User|null $request
 * @property-read User|null $confirm
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Batch> $batch
 */
class StockTransfers extends Model
{
    use HasFactory, HasUlids, SoftDeletes;

    protected $guarded = [
        'id',
    ];

    protected $casts = [
        'status' => TransferStatusCast::class,
    ];

    public $incrementing = false;
    public $keyType = 'string';

    public function fromWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'from_warehouse_id');
    }

    public function toWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'to_warehouse_id');
    }

    public function confirm(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function products(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function batch(): BelongsToMany
    {
        return $this->belongsToMany(Batch::class, 'stock_transfer_items', 'transfer_id', 'batch_id');
    }

    public function receivings(): MorphMany
    {
        return $this->morphMany(ProductReceiving::class, 'receivable');    
    }
}
