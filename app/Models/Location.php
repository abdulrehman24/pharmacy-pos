<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Location extends Model
{
    protected $fillable = ['name', 'code', 'is_default'];

    protected $casts = [
        'is_default' => 'boolean',
    ];

    public function stockLots(): HasMany
    {
        return $this->hasMany(StockLot::class);
    }
}
