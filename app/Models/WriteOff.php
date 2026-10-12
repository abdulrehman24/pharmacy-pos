<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WriteOff extends Model
{
    protected $fillable = [
        'write_off_number',
        'user_id',
        'approved_by',
        'total_loss_paisa',
        'type', // expired, damaged, lost
        'reason',
    ];

    protected $casts = [
        'total_loss_paisa' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(WriteOffLine::class);
    }
}
