<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

class PurchaseOrderItem extends Pivot
{
    use HasFactory, HasUlids;

    protected $table = 'purchase_order_items';

    protected $guarded = [
        'id'
    ];

    public $incrementing = false;
    public $keyType = 'string';

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');    
    }

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class, 'purchase_id');    
    }
}
