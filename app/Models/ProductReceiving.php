<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductReceiving extends Model
{
    use HasFactory, HasUlids, SoftDeletes;

    protected $guarded = [
        'id',
    ];

    public $incrementing = false;
    public $keyType = 'string';

    public function batch(): BelongsTo {
        return $this->belongsTo(Batch::class, 'receiving_id');
    }

    public function purchaseOrder(): BelongsTo {
        return $this->belongsTo(PurchaseOrder::class, 'purchase_id');
    }

    public function product(): BelongsToMany {
        return $this->belongsToMany(Product::class, 'product_receiving_items', 'product_id', 'receiving_id');
    }

    public function warehouse(): BelongsTo {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }
}
