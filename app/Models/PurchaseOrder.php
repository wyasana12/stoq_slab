<?php

namespace App\Models;

use App\Enums\PurchaseOrderStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseOrder extends Model
{
    use HasFactory, HasUlids, SoftDeletes;

    protected $guarded = [
        'id'
    ];

    protected $casts = [
        'approved_at' => 'datetime',
        'order_date' => 'datetime',
        'status' => PurchaseOrderStatus::class,
    ];

    public $incrementing = false;

    protected $keyType = 'string';

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class, 'purchase_id');
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'purchase_order_items', 'purchase_id', 'product_id')
            ->using(PurchaseOrderItem::class)
            ->withPivot([
                'id',
                'quantity_ordered',
                'quantity_received',
                'unit_price',
                'subtotal'
            ])
            ->wherePivotNull('deleted_at')
            ->withTimestamps();
    }

    public function receivings(): MorphMany
    {
        return $this->morphMany(ProductReceiving::class, 'receivable');    
    }
}
