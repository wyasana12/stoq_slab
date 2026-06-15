<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class StockDisposal extends Model
{
    use HasFactory, HasUlids, SoftDeletes;

    protected $fillable = [
        'disposal_code',
        'batch_id',
        'product_id',
        'warehouse_id',
        'requested_quantity',
        'approved_quantity',
        'reason',
        'requested_by',
        'confirmed_by',
        'damage_proof_path',
        'damage_proof_name',
        'damage_proof_mime',
        'damage_proof_size',
        'damage_proof_uploaded_at',
        'notes',
        'status',
    ];

    protected $casts = [
        'damage_proof_uploaded_at' => 'datetime',
        'requested_quantity' => 'integer',
        'approved_quantity' => 'integer',
    ];

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
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
