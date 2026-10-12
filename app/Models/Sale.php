<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sale extends Model
{
    protected $fillable = [
        'invoice_number',
        'idempotency_key',
        'shift_id',
        'counter_id',
        'cashier_id',
        'customer_id',
        'subtotal_paisa',
        'discount_paisa',
        'tax_paisa',
        'total_paisa',
        'received_paisa',
        'change_paisa',
        'balance_paisa',
        'payment_method',
        'payment_status',
        'status',
        'notes',
    ];

    protected $casts = [
        'subtotal_paisa' => 'integer',
        'discount_paisa' => 'integer',
        'tax_paisa' => 'integer',
        'total_paisa' => 'integer',
        'received_paisa' => 'integer',
        'change_paisa' => 'integer',
        'balance_paisa' => 'integer',
    ];

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    public function counter(): BelongsTo
    {
        return $this->belongsTo(Counter::class);
    }

    public function cashier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cashier_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(SaleLine::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(SaleAllocation::class);
    }

    public function returns(): HasMany
    {
        return $this->hasMany(SalesReturn::class);
    }

    public function printJobs(): HasMany
    {
        return $this->hasMany(PrintJob::class);
    }

    public function scopePosted(Builder $query): Builder
    {
        return $query->where('status', 'posted');
    }

    /**
     * Compute true Cost of Goods Sold from historical lot cost allocations
     */
    public function totalCogsPaisa(): int
    {
        return (int) round($this->allocations->sum(function ($alloc) {
            return $alloc->base_quantity * (float) $alloc->cost_per_base_unit_paisa;
        }));
    }

    /**
     * Gross margin = Net sale (total_paisa - tax_paisa) - COGS
     */
    public function grossMarginPaisa(): int
    {
        $netSale = $this->total_paisa - $this->tax_paisa;
        return $netSale - $this->totalCogsPaisa();
    }

    /**
     * Resolve route binding by primary key ID or unique invoice_number
     */
    public function resolveRouteBinding($value, $field = null)
    {
        if ($field) {
            return parent::resolveRouteBinding($value, $field);
        }
        return is_numeric($value)
            ? $this->where('id', $value)->firstOrFail()
            : $this->where('invoice_number', $value)->firstOrFail();
    }
}
