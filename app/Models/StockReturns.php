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

/**
 * @property string $id
 * @property string|null $receiving_id
 * @property string|null $product_id
 * @property string|null $warehouse_id
 * @property string|null $requested_by
 * @property string|null $confirmed_by
 * @property string|null $damage_proof_path
 * @property string|null $damage_proof_name
 * @property string|null $damage_proof_mime
 * @property int|null $damage_proof_size
 * @property \Illuminate\Support\Carbon|null $damage_proof_uploaded_at
 * @property int|null $requested_quantity
 * @property int|null $approved_quantity
 * @property string|null $notes
 * @property mixed|null $status
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read ProductReceiving|null $receiving
 * @property-read Product|null $product
 * @property-read Warehouse|null $warehouse
 * @property-read User|null $request
 * @property-read User|null $confirm
 */
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
        'damage_proof_uploaded_at' => 'datetime',
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
