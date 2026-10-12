<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MedicineBarcode extends Model
{
    protected $fillable = [
        'medicine_id',
        'medicine_package_id',
        'barcode',
    ];

    public function medicine(): BelongsTo
    {
        return $this->belongsTo(Medicine::class);
    }

    public function medicinePackage(): BelongsTo
    {
        return $this->belongsTo(MedicinePackage::class);
    }
}
