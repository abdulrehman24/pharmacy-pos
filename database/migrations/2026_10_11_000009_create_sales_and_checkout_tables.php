<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number', 30)->unique();
            $table->string('idempotency_key', 64)->unique();
            $table->foreignId('shift_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('counter_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('cashier_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->bigInteger('subtotal_paisa')->default(0);
            $table->bigInteger('discount_paisa')->default(0);
            $table->bigInteger('tax_paisa')->default(0);
            $table->bigInteger('total_paisa')->default(0);
            $table->bigInteger('received_paisa')->default(0);
            $table->bigInteger('change_paisa')->default(0);
            $table->bigInteger('balance_paisa')->default(0); // If credit or partial
            $table->string('payment_method', 30)->default('cash'); // cash, card, jazzcash, easypaisa, bank_transfer, credit, split
            $table->string('payment_status', 20)->default('paid'); // paid, partial, unpaid
            $table->string('status', 20)->default('posted'); // posted, cancelled, returned
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['shift_id', 'status']);
            $table->index(['cashier_id', 'created_at']);
            $table->index(['customer_id', 'created_at']);
        });

        Schema::create('sale_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained()->cascadeOnDelete();
            $table->foreignId('medicine_id')->constrained()->cascadeOnDelete();
            $table->foreignId('medicine_package_id')->nullable()->constrained()->nullOnDelete();
            $table->string('medicine_name'); // Snapshot
            $table->string('package_name'); // Snapshot
            $table->unsignedInteger('conversion_factor')->default(1);
            $table->unsignedInteger('quantity');
            $table->unsignedInteger('total_base_quantity');
            $table->bigInteger('unit_price_paisa'); // Snapshot
            $table->bigInteger('subtotal_paisa');
            $table->bigInteger('discount_paisa')->default(0);
            $table->bigInteger('tax_paisa')->default(0);
            $table->bigInteger('total_paisa');
            $table->timestamps();
        });

        Schema::create('sale_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sale_line_id')->constrained()->cascadeOnDelete();
            $table->foreignId('stock_lot_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('base_quantity');
            $table->decimal('cost_per_base_unit_paisa', 14, 4); // Snapshot historical cost at time of sale
            $table->timestamps();

            $table->index(['sale_id', 'stock_lot_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_allocations');
        Schema::dropIfExists('sale_lines');
        Schema::dropIfExists('sales');
    }
};
