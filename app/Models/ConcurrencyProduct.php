<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ConcurrencyProduct extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'stock',
    ];

    public function tests(): HasMany
    {
        return $this->hasMany(ConcurrencyTest::class);
    }
}
