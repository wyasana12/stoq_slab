<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string|null $batch_code
 * @property string|null $warehouse_id
 * @property string|null $product_id
 * @property int|null $current_quantity
 * @property mixed|null $production_date
 * @property mixed|null $expired_date
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Warehouse|null $warehouse
 * @property-read Product|null $product
 */
class Batch extends Model
{
    use HasFactory, HasUlids;

    protected $guarded = [
        'id'
    ];

    protected $casts = [
        'production_date' => 'datetime',
        'expired_date' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public $incrementing = false;

    protected $keyType = 'string';

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function receive(): BelongsTo
    {
        return $this->belongsTo(ProductReceiving::class, 'receiving_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }

    public function alerts(): HasMany
    {
        return $this->hasMany(AlertLog::class, 'batch_id');    
    }
    
    public function locations(): HasMany
    {
        return $this->hasMany(RackLocation::class, 'batch_id');    
    }
}
