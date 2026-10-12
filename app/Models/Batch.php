<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Batch extends Model
{
    protected $fillable = [
        'medicine_id',
        'batch_number',
        'expiry_date',
        'status', // sellable, quarantine, recalled, expired
    ];

    protected $casts = [
        'expiry_date' => 'date',
    ];

    public function medicine(): BelongsTo
    {
        return $this->belongsTo(Medicine::class);
    }

    public function stockLots(): HasMany
    {
        return $this->hasMany(StockLot::class);
    }

    public function isExpired(): bool
    {
        return $this->expiry_date->isPast();
    }

    public function isSellable(): bool
    {
        return $this->status === 'sellable' && !$this->isExpired();
    }

    public function scopeSellable(Builder $query): Builder
    {
        return $query->where('status', 'sellable')
            ->whereDate('expiry_date', '>=', now()->toDateString());
    }

    public function scopeFefo(Builder $query): Builder
    {
        return $query->orderBy('expiry_date', 'asc')->orderBy('id', 'asc');
    }
}
