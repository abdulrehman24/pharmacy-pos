<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Purchase extends Model
{
    protected $fillable = [
        'invoice_number',
        'supplier_id',
        'user_id',
        'supplier_invoice_number',
        'invoice_date',
        'subtotal_paisa',
        'discount_paisa',
        'tax_paisa',
        'total_paisa',
        'paid_paisa',
        'payment_method',
        'status', // draft, posted, cancelled
        'notes',
    ];

    protected $casts = [
        'invoice_date' => 'date',
        'subtotal_paisa' => 'integer',
        'discount_paisa' => 'integer',
        'tax_paisa' => 'integer',
        'total_paisa' => 'integer',
        'paid_paisa' => 'integer',
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PurchaseLine::class);
    }
}
