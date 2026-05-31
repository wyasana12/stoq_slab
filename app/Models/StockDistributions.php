<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $id
 * @property string|null $distribution_code
 * @property string|null $warehouse_id
 * @property string|null $location
 * @property string|null $outlet_name
 * @property string|null $outlet_address
 * @property string|null $outlet_phone
 * @property string|null $outlet_contact
 * @property string|null $requested_by
 * @property string|null $confirmed_by
 * @property string|null $notes
 * @property string|null $status
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Warehouse|null $warehouse
 * @property-read User|null $request
 * @property-read User|null $confirmedBy
 * @property-read \Illuminate\Database\Eloquent\Collection<int, StockDistributionItem> $items
 */
class StockDistributions extends Model
{
    use HasFactory, HasUlids, SoftDeletes;

    protected $guarded = [
        'id'
    ];

    public $incrementing = false;
    public $keyType = 'string';

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function confirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(StockDistributionItem::class, 'distribution_id');
    }
}
