<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RackLocation extends Model
{
    use HasFactory, HasUlids;

    protected $guarded = [
        'id'
    ];

    public $incrementing = false;

    protected $keyType = 'string';

    public function rack(): BelongsTo
    {
        return $this->belongsTo(RackWarehouse::class, 'rack_id');    
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class, 'batch_id');    
    }
    
    // public function capacities(): HasMany
    // {
    //     return $this->hasMany(RackLocationCapacity::class, 'location_id');    
    // }
}
