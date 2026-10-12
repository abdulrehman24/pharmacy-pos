<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WriteOffLine extends Model
{
    protected $fillable = [
        'write_off_id',
        'stock_lot_id',
        'medicine_id',
        'base_quantity',
        'unit_cost_paisa',
        'total_loss_paisa',
    ];

    protected $casts = [
        'base_quantity' => 'integer',
        'unit_cost_paisa' => 'decimal:4',
        'total_loss_paisa' => 'integer',
    ];

    public function writeOff(): BelongsTo
    {
        return $this->belongsTo(WriteOff::class);
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
