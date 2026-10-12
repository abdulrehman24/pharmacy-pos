<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_returns', function (Blueprint $table) {
            $table->id();
            $table->string('return_number', 30)->unique();
            $table->foreignId('sale_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('cashier_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('shift_id')->nullable()->constrained()->nullOnDelete();
            $table->bigInteger('total_refund_paisa');
            $table->string('refund_method', 30)->default('cash'); // cash, credit_adjustment
            $table->text('reason');
            $table->string('status', 20)->default('completed');
            $table->timestamps();

            $table->index(['sale_id', 'created_at']);
        });

        Schema::create('sales_return_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_return_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sale_line_id')->constrained()->cascadeOnDelete();
            $table->foreignId('medicine_id')->constrained()->cascadeOnDelete();
            $table->foreignId('stock_lot_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('quantity'); // Package quantity returned
            $table->unsignedInteger('base_quantity');
            $table->string('disposition', 30)->default('restock'); // restock, quarantine, write_off
            $table->bigInteger('refund_paisa');
            $table->timestamps();
        });

        Schema::create('supplier_returns', function (Blueprint $table) {
            $table->id();
            $table->string('return_number', 30)->unique();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->bigInteger('total_credit_paisa');
            $table->text('reason');
            $table->string('status', 20)->default('completed'); // completed, pending_credit
            $table->timestamps();
        });

        Schema::create('supplier_return_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_return_id')->constrained()->cascadeOnDelete();
            $table->foreignId('stock_lot_id')->constrained()->cascadeOnDelete();
            $table->foreignId('medicine_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('base_quantity');
            $table->decimal('unit_cost_paisa', 14, 4);
            $table->bigInteger('total_paisa');
            $table->timestamps();
        });

        Schema::create('write_offs', function (Blueprint $table) {
            $table->id();
            $table->string('write_off_number', 30)->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->bigInteger('total_loss_paisa');
            $table->string('type', 30)->default('expired'); // expired, damaged, lost
            $table->text('reason');
            $table->timestamps();
        });

        Schema::create('write_off_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('write_off_id')->constrained()->cascadeOnDelete();
            $table->foreignId('stock_lot_id')->constrained()->cascadeOnDelete();
            $table->foreignId('medicine_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('base_quantity');
            $table->decimal('unit_cost_paisa', 14, 4);
            $table->bigInteger('total_loss_paisa');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('write_off_lines');
        Schema::dropIfExists('write_offs');
        Schema::dropIfExists('supplier_return_lines');
        Schema::dropIfExists('supplier_returns');
        Schema::dropIfExists('sales_return_lines');
        Schema::dropIfExists('sales_returns');
    }
};
