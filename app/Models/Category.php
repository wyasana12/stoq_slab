<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Category extends Model
{
    use HasFactory, HasUlids, SoftDeletes;

    protected $guarded = [
        'id'
    ];

    public $incrementing = false;

    protected $keyType = 'string';

    public function batches(): HasMany
    {
        return $this->hasMany(Batch::class, 'category_id');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'category_id');
    }
}
