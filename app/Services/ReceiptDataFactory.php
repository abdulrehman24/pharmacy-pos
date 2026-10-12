<?php

namespace App\Services;

use App\Models\Sale;
use App\Models\Setting;
use Carbon\Carbon;

class ReceiptDataFactory
{
    public function forSale(Sale $sale, bool $isCopy = false, ?int $paperWidth = null): array
    {
        $sale->loadMissing(['lines', 'cashier', 'customer']);

        $shop = [
            'name' => (string) Setting::get('shop_name', 'Al-Shifa Community Pharmacy'),
            'address' => (string) Setting::get('shop_address', 'Main Boulevard, Gulberg III, Lahore'),
            'phone' => (string) Setting::get('shop_phone', '+92 42 35789000'),
            'footer' => (string) Setting::get('receipt_footer', 'Thank you for your visit! Medicines once sold cannot be returned without original receipt within 7 days.'),
        ];

        $effectivePaperWidth = $paperWidth ?: (int) Setting::get('default_paper_width', 80);

        $items = $sale->lines->map(function ($line) {
            return [
                'id' => (int) $line->id,
                'name' => (string) $line->medicine_name,
                'quantity' => (int) $line->quantity,
                'unit' => (string) $line->package_name,
                'price_paisa' => (int) $line->unit_price_paisa,
                'total_paisa' => (int) $line->total_paisa,
            ];
        })->values()->all();

        $createdAt = Carbon::parse($sale->created_at)->timezone('Asia/Karachi');

        $paymentLabel = match ($sale->payment_method) {
            'card' => 'Debit/Credit Card',
            'credit' => 'Customer Credit (Khata)',
            'jazzcash' => 'JazzCash',
            'easypaisa' => 'Easypaisa',
            'bank_transfer' => 'Bank Transfer',
            default => 'Cash',
        };

        if ($sale->customer) {
            $paymentLabel .= ' (' . $sale->customer->name . ')';
        }

        $saleData = [
            'id' => (int) $sale->id,
            'number' => (string) $sale->invoice_number,
            'timestamp' => $createdAt->toIso8601String(),
            'local_datetime' => $createdAt->format('d-M-Y h:i A'),
            'cashier' => (string) ($sale->cashier?->name ?? 'Cashier'),
            'copy' => $isCopy,
            'items' => $items,
            'subtotal_paisa' => (int) $sale->subtotal_paisa,
            'discount_paisa' => (int) $sale->discount_paisa,
            'tax_paisa' => (int) $sale->tax_paisa,
            'total_paisa' => (int) $sale->total_paisa,
            'received_paisa' => (int) $sale->received_paisa,
            'change_paisa' => (int) $sale->change_paisa,
            'balance_paisa' => (int) $sale->balance_paisa,
            'payment_label' => $paymentLabel,
        ];

        return [
            'sale' => $saleData,
            'shop' => $shop,
            'paperWidth' => in_array($effectivePaperWidth, [58, 80], true) ? $effectivePaperWidth : 80,
        ];
    }
}
