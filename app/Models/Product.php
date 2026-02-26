<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, HasUlids, SoftDeletes;

    protected $guarded = [
        'id'
    ];

    public $incrementing = false;

    protected $keyType = 'string';

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'unit_id');
    }

    public function purchaseItem(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class, 'product_id');
    }

    public function restock(): BelongsToMany
    {
        return $this->belongsToMany(Restock::class, 'stock_restock_items', 'product_id', 'restock_id');
    }

    public function receivingItem(): HasMany
    {
        return $this->hasMany(ProductReceivingItem::class, 'product_id', 'receiving_id');
    }

    public function batch(): HasMany
    {
        return $this->hasMany(Batch::class, 'product_id');
    }
}
