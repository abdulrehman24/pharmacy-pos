<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseLine extends Model
{
    protected $fillable = [
        'purchase_id',
        'medicine_id',
        'medicine_package_id',
        'batch_number',
        'expiry_date',
        'package_quantity',
        'bonus_base_quantity',
        'total_base_quantity',
        'package_cost_paisa',
        'unit_cost_paisa',
        'total_cost_paisa',
    ];

    protected $casts = [
        'expiry_date' => 'date',
        'package_quantity' => 'integer',
        'bonus_base_quantity' => 'integer',
        'total_base_quantity' => 'integer',
        'package_cost_paisa' => 'integer',
        'unit_cost_paisa' => 'decimal:4',
        'total_cost_paisa' => 'integer',
    ];

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
    }

    public function medicine(): BelongsTo
    {
        return $this->belongsTo(Medicine::class);
    }

    public function medicinePackage(): BelongsTo
    {
        return $this->belongsTo(MedicinePackage::class);
    }
}
