<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RackLocationCapacity extends Model
{
    use HasFactory, HasUlids;

    protected $guarded = [
        'id'
    ];

    public $incrementing = false;

    protected $keyType = 'string';

    public function unit(): BelongsTo {
        return $this->belongsTo(Unit::class, 'unit_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(RackLocation::class, 'location_id');    
    }
}
