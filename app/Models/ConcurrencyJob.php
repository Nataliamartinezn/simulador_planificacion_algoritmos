<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ConcurrencyJob extends Model
{
    use HasFactory;

    protected $fillable = [
        'concurrency_test_id',
        'order_number',
        'quantity',
        'pid',
        'worker',
        'status',
        'lock_status',
        'stock_read',
        'stock_written',
        'error_message',
        'started_at',
        'finished_at',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function test(): BelongsTo
    {
        return $this->belongsTo(ConcurrencyTest::class, 'concurrency_test_id');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(ConcurrencyLog::class);
    }
}
