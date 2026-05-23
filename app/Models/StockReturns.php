<?php

namespace App\Models;

use App\Models\Product;
use App\Models\ProductReceiving;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class StockReturns extends Model
{
    use HasFactory, HasUlids, SoftDeletes;

    protected $table = 'stock_returns';
    protected $guarded = ['id'];
    public $incrementing = false;
    protected $keyType = 'string';

    protected $casts = [
        'requested_quantity' => 'integer',
        'approved_quantity' => 'integer',
    ];

    public function receiving(): BelongsTo
    {
        return $this->belongsTo(ProductReceiving::class, 'receiving_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function confirm(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }
}
