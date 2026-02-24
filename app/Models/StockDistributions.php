<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class StockDistributions extends Model
{
    use HasFactory, HasUlids, SoftDeletes;

    protected $guarded = [
        'id'
    ];

    public $incrementing = false;
    public $keyType = 'string';

    public function warehouse(): HasMany
    {
        return $this->hasMany(Warehouse::class, 'warehouse_id');
    }

    public function confirm(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function batch(): BelongsToMany
    {
        return $this->belongsToMany(Batch::class, 'stock_distribution_items', 'batch_id', 'distribution_id');
    }
}
