<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierReturnLine extends Model
{
    protected $fillable = [
        'supplier_return_id',
        'stock_lot_id',
        'medicine_id',
        'base_quantity',
        'unit_cost_paisa',
        'total_paisa',
    ];

    protected $casts = [
        'base_quantity' => 'integer',
        'unit_cost_paisa' => 'decimal:4',
        'total_paisa' => 'integer',
    ];

    public function supplierReturn(): BelongsTo
    {
        return $this->belongsTo(SupplierReturn::class);
    }

    public function stockLot(): BelongsTo
    {
        return $this->belongsTo(StockLot::class);
    }

    public function medicine(): BelongsTo
    {
        return $this->belongsTo(Medicine::class);
    }
}
