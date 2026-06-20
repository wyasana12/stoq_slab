<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class RestockItem extends Model
{
    use HasFactory, HasUlids, SoftDeletes;

    protected $table = 'restock_items';
    protected $guarded = [
        'id'
    ];

    public $incrementing = false;
    public $keyType = 'string';

    public function restock(): BelongsTo
    {
        return $this->belongsTo(Restock::class, 'restock_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}
