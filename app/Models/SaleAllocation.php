<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaleAllocation extends Model
{
    protected $fillable = [
        'sale_id',
        'sale_line_id',
        'stock_lot_id',
        'base_quantity',
        'cost_per_base_unit_paisa',
    ];

    protected $casts = [
        'base_quantity' => 'integer',
        'cost_per_base_unit_paisa' => 'decimal:4',
    ];

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function saleLine(): BelongsTo
    {
        return $this->belongsTo(SaleLine::class);
    }

    public function stockLot(): BelongsTo
    {
        return $this->belongsTo(StockLot::class);
    }
}
