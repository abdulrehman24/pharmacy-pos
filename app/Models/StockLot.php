<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockLot extends Model
{
    protected $fillable = [
        'batch_id',
        'location_id',
        'receipt_reference',
        'purchase_price_paisa',
        'cost_per_base_unit',
        'received_base_qty',
        'remaining_base_qty',
    ];

    protected $casts = [
        'purchase_price_paisa' => 'integer',
        'cost_per_base_unit' => 'decimal:4',
        'received_base_qty' => 'integer',
        'remaining_base_qty' => 'integer',
    ];

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function saleAllocations(): HasMany
    {
        return $this->hasMany(SaleAllocation::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function scopeWithStock(Builder $query): Builder
    {
        return $query->where('remaining_base_qty', '>', 0);
    }
}
