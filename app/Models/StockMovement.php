<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMovement extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'medicine_id',
        'batch_id',
        'stock_lot_id',
        'location_id',
        'movement_type', // opening, purchase, sale, customer_return, supplier_return, adjustment, write_off
        'reference_type',
        'reference_id',
        'quantity_base_unit',
        'cost_per_base_unit',
        'user_id',
        'notes',
        'created_at',
    ];

    protected $casts = [
        'quantity_base_unit' => 'integer',
        'cost_per_base_unit' => 'decimal:4',
        'created_at' => 'datetime',
    ];

    public function medicine(): BelongsTo
    {
        return $this->belongsTo(Medicine::class);
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    public function stockLot(): BelongsTo
    {
        return $this->belongsTo(StockLot::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
