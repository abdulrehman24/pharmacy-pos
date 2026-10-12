<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MedicinePackage extends Model
{
    protected $fillable = [
        'medicine_id',
        'name',
        'base_quantity',
        'default_sell_price_paisa',
        'default_purchase_price_paisa',
        'is_default_sale',
        'is_default_purchase',
    ];

    protected $casts = [
        'base_quantity' => 'integer',
        'default_sell_price_paisa' => 'integer',
        'default_purchase_price_paisa' => 'integer',
        'is_default_sale' => 'boolean',
        'is_default_purchase' => 'boolean',
    ];

    public function medicine(): BelongsTo
    {
        return $this->belongsTo(Medicine::class);
    }

    public function barcodes(): HasMany
    {
        return $this->hasMany(MedicineBarcode::class);
    }
}
