<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SaleLine extends Model
{
    protected $fillable = [
        'sale_id',
        'medicine_id',
        'medicine_package_id',
        'medicine_name',
        'package_name',
        'conversion_factor',
        'quantity',
        'total_base_quantity',
        'unit_price_paisa',
        'subtotal_paisa',
        'discount_paisa',
        'tax_paisa',
        'total_paisa',
    ];

    protected $casts = [
        'conversion_factor' => 'integer',
        'quantity' => 'integer',
        'total_base_quantity' => 'integer',
        'unit_price_paisa' => 'integer',
        'subtotal_paisa' => 'integer',
        'discount_paisa' => 'integer',
        'tax_paisa' => 'integer',
        'total_paisa' => 'integer',
    ];

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function medicine(): BelongsTo
    {
        return $this->belongsTo(Medicine::class);
    }

    public function medicinePackage(): BelongsTo
    {
        return $this->belongsTo(MedicinePackage::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(SaleAllocation::class);
    }

    public function returnLines(): HasMany
    {
        return $this->hasMany(SalesReturnLine::class);
    }

    public function returnedQuantity(): int
    {
        return (int) $this->returnLines()->sum('quantity');
    }

    public function remainingReturnableQty(): int
    {
        return max(0, $this->quantity - $this->returnedQuantity());
    }
}
