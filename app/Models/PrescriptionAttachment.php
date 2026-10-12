<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrescriptionAttachment extends Model
{
    protected $fillable = [
        'prescription_id',
        'file_path',
        'file_name',
        'mime_type',
        'file_size',
    ];

    public function prescription(): BelongsTo
    {
        return $this->belongsTo(Prescription::class);
    }
}
