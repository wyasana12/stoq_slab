<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductReceiving extends Model
{
    use HasFactory, HasUlids;

    protected $guarded = [
        'id',
    ];

    public $incrementing = false;
    public $keyType = 'string';

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class, 'receiving_id');
    }

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class, 'purchase_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(ProductReceivingItem::class, 'receiving_id', 'product_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }
}
