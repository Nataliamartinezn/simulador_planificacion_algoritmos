<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConcurrencyLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'concurrency_test_id',
        'concurrency_job_id',
        'pid',
        'event',
        'message',
    ];

    public function test(): BelongsTo
    {
        return $this->belongsTo(ConcurrencyTest::class, 'concurrency_test_id');
    }

    public function job(): BelongsTo
    {
        return $this->belongsTo(ConcurrencyJob::class, 'concurrency_job_id');
    }
}
