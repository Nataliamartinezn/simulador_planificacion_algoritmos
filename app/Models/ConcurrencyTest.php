<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ConcurrencyTest extends Model
{
    use HasFactory;

    protected $fillable = [
        'concurrency_product_id',
        'method',
        'initial_stock',
        'order_count',
        'quantity_per_order',
        'total_requested',
        'expected_stock',
        'final_stock',
        'status',
        'elapsed_seconds',
        'started_at',
        'finished_at',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'elapsed_seconds' => 'decimal:2',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(ConcurrencyProduct::class, 'concurrency_product_id');
    }

    public function jobs(): HasMany
    {
        return $this->hasMany(ConcurrencyJob::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(ConcurrencyLog::class);
    }
}
