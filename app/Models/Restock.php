<?php

namespace App\Models;

use App\Casts\RestockStatusCast;
use App\Enums\RestockStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Restock extends Model
{
    use HasFactory, HasUlids, SoftDeletes;

    protected $guarded = [
        'id'
    ];

    protected $casts = [
        'status' => RestockStatusCast::class,
    ];

    public $incrementing = false;
    public $keyType = 'string';

    public function item(): HasMany
    {
        return $this->hasMany(RestockItem::class, 'restock_id');
    }

    public function confirm(): BelongsTo
    {
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
