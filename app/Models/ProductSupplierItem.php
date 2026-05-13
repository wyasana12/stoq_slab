<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductSupplierItem extends Model
{
    use HasFactory, HasUlids, SoftDeletes;

    protected $guarded = [
        'id'
    ];

    public $incrementing = false;
    public $keyType = 'string';

    protected static function booted()
    {
        static::saving(function (ProductSupplierItem $item) {
            if ($item->is_preferred) {
                static::where('product_id', $item->product_id)
                    ->where('id', '!=', $item->id)
                    ->update(['is_preferred' => false]);
            }
        });
    }
    
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }
}
