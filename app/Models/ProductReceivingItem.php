<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductReceivingItem extends Model
{
    use HasFactory, HasUlids;

    protected $guarded = [
        'id'
    ];

    public $incrementing = false;
    public $keyType = 'string';

    public function receiving(): BelongsTo
    {
        return $this->belongsTo(ProductReceiving::class, 'receiving_id');    
    }

    public function products(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');    
    }
}
