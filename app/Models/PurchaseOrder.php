<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseOrder extends Model
{
    use HasFactory, HasUlids, SoftDeletes;

    protected $guarded = [
        'id'
    ];

    public $incrementing = false;

    protected $keyType = 'string';

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');    
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');    
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');    
    }

    public function product(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'purchase_order_items', 'product_id', 'purchase_id');    
    }
}
