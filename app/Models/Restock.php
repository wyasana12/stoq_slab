<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Restock extends Model
{
    use HasFactory, HasUlids, SoftDeletes;

    protected $guarded = [
        'id'
    ];

    public $incrementing = false;
    public $keyType = 'string';

    public function product(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'stock_restock_items', 'product_id', 'restock_id');    
    }

    public function confirm(): BelongsTo {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');    
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');    
    }
}
