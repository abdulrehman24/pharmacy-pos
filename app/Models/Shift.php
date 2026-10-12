<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Shift extends Model
{
    protected $fillable = [
        'counter_id',
        'user_id',
        'opening_float_paisa',
        'opened_at',
        'closed_at',
        'counted_cash_paisa',
        'expected_cash_paisa',
        'variance_paisa',
        'notes',
        'status',
    ];

    protected $casts = [
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public function counter(): BelongsTo
    {
        return $this->belongsTo(Counter::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    public function returns(): HasMany
    {
        return $this->hasMany(SalesReturn::class);
    }

    public function cashMovements(): HasMany
    {
        return $this->hasMany(CashMovement::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    public function customerPayments(): HasMany
    {
        return $this->hasMany(CustomerPayment::class);
    }

    /**
     * Expected cash = opening float + cash sales + customer cash payments + cash in
     *                 - cash returns - expenses - cash out
     */
    public function calculateExpectedCashPaisa(): int
    {
        $opening = (int) $this->opening_float_paisa;

        // Only count CASH sales (or cash portion)
        $cashSales = (int) $this->sales()
            ->where('status', 'posted')
            ->where(function ($q) {
                $q->where('payment_method', 'cash')
                  ->orWhere('payment_method', 'split');
            })
            ->sum('received_paisa');

        // Subtract change given out on sales
        $changeGiven = (int) $this->sales()
            ->where('status', 'posted')
            ->sum('change_paisa');

        $netCashFromSales = $cashSales - $changeGiven;

        // Customer credit collections in cash during this shift
        $customerCashPayments = (int) $this->customerPayments()
            ->where('payment_method', 'cash')
            ->sum('amount_paisa');

        // Cash In movements
        $cashIn = (int) $this->cashMovements()
            ->where('type', 'cash_in')
            ->sum('amount_paisa');

        // Cash Returns paid out to customers
        $cashReturns = (int) $this->returns()
            ->where('refund_method', 'cash')
            ->sum('total_refund_paisa');

        // Cash Out movements
        $cashOut = (int) $this->cashMovements()
            ->where('type', 'cash_out')
            ->sum('amount_paisa');

        // Expenses paid from drawer
        $expenses = (int) $this->expenses()->sum('amount_paisa');

        return $opening + $netCashFromSales + $customerCashPayments + $cashIn - $cashReturns - $cashOut - $expenses;
    }
}
