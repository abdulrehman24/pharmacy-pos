<?php

namespace App\Services;

use App\Models\Medicine;
use App\Models\MedicinePackage;
use App\Models\Setting;
use InvalidArgumentException;

class PricingService
{
    /**
     * Authoritatively resolve prices, quantities, and line totals for checkout
     */
    public function resolveItems(array $rawItems): array
    {
        $resolved = [];

        foreach ($rawItems as $raw) {
            $medicineId = (int) ($raw['medicine_id'] ?? 0);
            $packageId = isset($raw['medicine_package_id']) ? (int) $raw['medicine_package_id'] : null;
            $quantity = (int) ($raw['quantity'] ?? 1);
            $lineDiscountPaisa = (int) ($raw['discount_paisa'] ?? 0);

            if ($quantity <= 0) {
                throw new InvalidArgumentException("Quantity must be a positive integer.");
            }

            $medicine = Medicine::findOrFail($medicineId);

            if (!$medicine->is_active) {
                throw new InvalidArgumentException("Medicine '{$medicine->brand_name}' is inactive.");
            }

            $package = null;
            if ($packageId) {
                $package = MedicinePackage::where('medicine_id', $medicine->id)
                    ->where('id', $packageId)
                    ->firstOrFail();
            } else {
                // Fallback to default sale package or first package
                $package = MedicinePackage::where('medicine_id', $medicine->id)
                    ->where('is_default_sale', true)
                    ->first() ?? MedicinePackage::where('medicine_id', $medicine->id)->firstOrFail();
            }

            $unitPricePaisa = $package->default_sell_price_paisa;
            // Overrides require explicit permission checked at request level
            if (isset($raw['unit_price_paisa']) && is_numeric($raw['unit_price_paisa']) && $raw['unit_price_paisa'] > 0) {
                $unitPricePaisa = (int) $raw['unit_price_paisa'];
            }

            $conversionFactor = $package->base_quantity;
            $totalBaseQty = $quantity * $conversionFactor;
            $subtotalPaisa = $quantity * $unitPricePaisa;

            if ($lineDiscountPaisa > $subtotalPaisa) {
                $lineDiscountPaisa = $subtotalPaisa;
            }

            $lineTotalPaisa = $subtotalPaisa - $lineDiscountPaisa;

            $resolved[] = [
                'medicine_id' => $medicine->id,
                'medicine_package_id' => $package->id,
                'medicine_name' => $medicine->brand_name . ($medicine->strength ? " ({$medicine->strength})" : ''),
                'package_name' => $package->name,
                'conversion_factor' => $conversionFactor,
                'quantity' => $quantity,
                'total_base_quantity' => $totalBaseQty,
                'unit_price_paisa' => $unitPricePaisa,
                'subtotal_paisa' => $subtotalPaisa,
                'discount_paisa' => $lineDiscountPaisa,
                'tax_paisa' => 0,
                'total_paisa' => $lineTotalPaisa,
            ];
        }

        return $resolved;
    }

    /**
     * Compute total summary for the entire cart
     */
    public function computeTotals(array $resolvedItems, int $invoiceDiscountPaisa = 0): array
    {
        $subtotalPaisa = 0;
        $itemsDiscountPaisa = 0;

        foreach ($resolvedItems as $item) {
            $subtotalPaisa += $item['subtotal_paisa'];
            $itemsDiscountPaisa += $item['discount_paisa'];
        }

        $totalDiscountPaisa = $itemsDiscountPaisa + $invoiceDiscountPaisa;
        if ($totalDiscountPaisa > $subtotalPaisa) {
            $totalDiscountPaisa = $subtotalPaisa;
        }

        $taxPercentage = (int) Setting::get('tax_percentage', 0);
        $taxableAmount = max(0, $subtotalPaisa - $totalDiscountPaisa);
        $taxPaisa = (int) round(($taxableAmount * $taxPercentage) / 100);

        $grandTotalPaisa = $taxableAmount + $taxPaisa;

        return [
            'subtotal_paisa' => $subtotalPaisa,
            'discount_paisa' => $totalDiscountPaisa,
            'tax_paisa' => $taxPaisa,
            'total_paisa' => $grandTotalPaisa,
        ];
    }
}
