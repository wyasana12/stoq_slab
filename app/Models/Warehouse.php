<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $name
 * @property string|null $region_id
 * @property-read Region|null $region
 */
class Warehouse extends Model
{
    use HasFactory, HasUlids;

    protected $guarded = [
        'id'
    ];

    public $incrementing = false;

    protected $keyType = 'string';

    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class, 'region_id');
    }

    public function store(): HasMany
    {
        return $this->hasMany(Store::class, 'warehouse_id');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'warehouse_id');    
    }

    public function admins(): HasMany
    {
        return $this->hasMany(User::class, 'warehouse_id')->role('admin');    
    }

    public function staffs(): HasMany
    {
        return $this->hasMany(User::class, 'warehouse_id')->role('staff');
    }
}
