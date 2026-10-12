<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Medicine extends Model
{
    protected $fillable = [
        'code',
        'brand_name',
        'generic_name',
        'strength',
        'dosage_form',
        'category_id',
        'manufacturer_id',
        'base_unit',
        'rack_shelf',
        'reorder_level',
        'prescription_required',
        'is_narcotic',
        'is_active',
        'notes',
    ];

    protected $casts = [
        'reorder_level' => 'integer',
        'prescription_required' => 'boolean',
        'is_narcotic' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function manufacturer(): BelongsTo
    {
        return $this->belongsTo(Manufacturer::class);
    }

    public function packages(): HasMany
    {
        return $this->hasMany(MedicinePackage::class);
    }

    public function barcodes(): HasMany
    {
        return $this->hasMany(MedicineBarcode::class);
    }

    public function batches(): HasMany
    {
        return $this->hasMany(Batch::class);
    }

    public function stockLots(): HasManyThrough
    {
        return $this->hasManyThrough(StockLot::class, Batch::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function totalSellableStockBaseUnit(): int
    {
        $today = now()->format('Y-m-d');

        return (int) StockLot::whereHas('batch', function ($q) use ($today) {
            $q->where('medicine_id', $this->id)
              ->where('status', 'sellable')
              ->where('expiry_date', '>=', $today);
        })->sum('remaining_base_qty');
    }
}
