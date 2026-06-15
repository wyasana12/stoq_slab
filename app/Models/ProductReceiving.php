<?php

namespace App\Models;

use App\Enums\ReceiveStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductReceiving extends Model
{
    use HasFactory, HasUlids, SoftDeletes;

    protected $guarded = [
        'id',
    ];

    protected $casts = [
        'receiving_date' => 'datetime',
        'status' => ReceiveStatus::class,
    ];

    public $incrementing = false;
    public $keyType = 'string';

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class, 'receiving_id');
    }

    public function receivable(): MorphTo
    {
        return $this->morphTo();
    }

    public function items(): HasMany
    {
        return $this->hasMany(ProductReceivingItem::class, 'receiving_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'receiving_by');
    }
}
