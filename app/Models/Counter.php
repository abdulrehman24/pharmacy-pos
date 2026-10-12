<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Counter extends Model
{
    protected $fillable = ['code', 'name', 'ip_address', 'is_active'];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function shifts(): HasMany
    {
        return $this->hasMany(Shift::class);
    }

    public function currentShift(): ?Shift
    {
        return $this->shifts()->where('status', 'open')->latest()->first();
    }
}
