<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('locations', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50);
            $table->string('code', 20)->unique();
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });

        Schema::create('batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('medicine_id')->constrained()->cascadeOnDelete();
            $table->string('batch_number', 50);
            $table->date('expiry_date')->index();
            $table->string('status', 20)->default('sellable'); // sellable, quarantine, recalled, expired
            $table->timestamps();

            $table->unique(['medicine_id', 'batch_number']);
            $table->index(['medicine_id', 'status', 'expiry_date']);
        });

        Schema::create('stock_lots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('location_id')->constrained()->cascadeOnDelete();
            $table->string('receipt_reference', 100)->nullable();
            $table->bigInteger('purchase_price_paisa')->default(0);
            $table->decimal('cost_per_base_unit', 14, 4)->default(0.0000); // Precise cost per single tablet/ml even with bonus
            $table->unsignedInteger('received_base_qty')->default(0);
            $table->unsignedInteger('remaining_base_qty')->default(0)->index();
            $table->timestamps();

            $table->index(['batch_id', 'remaining_base_qty']);
        });

        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('medicine_id')->constrained()->cascadeOnDelete();
            $table->foreignId('batch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('stock_lot_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('location_id')->constrained()->cascadeOnDelete();
            $table->string('movement_type', 30); // opening, purchase, sale, customer_return, supplier_return, adjustment, write_off
            $table->string('reference_type')->nullable(); // Sale, Purchase, SalesReturn, WriteOff, StockCount
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->integer('quantity_base_unit'); // Positive for addition, negative for deduction
            $table->decimal('cost_per_base_unit', 14, 4)->default(0.0000);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['medicine_id', 'movement_type', 'created_at']);
            $table->index(['reference_type', 'reference_id']);
        });

        Schema::create('stock_counts', function (Blueprint $table) {
            $table->id();
            $table->string('count_number', 30)->unique();
            $table->foreignId('location_id')->constrained()->cascadeOnDelete();
            $table->string('status', 20)->default('draft'); // draft, approved, rejected
            $table->foreignId('conducted_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
        });

        Schema::create('stock_count_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_count_id')->constrained()->cascadeOnDelete();
            $table->foreignId('medicine_id')->constrained()->cascadeOnDelete();
            $table->foreignId('batch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('stock_lot_id')->constrained()->cascadeOnDelete();
            $table->integer('system_quantity');
            $table->integer('counted_quantity');
            $table->integer('variance_quantity');
            $table->decimal('cost_per_base_unit', 14, 4)->default(0.0000);
            $table->string('reason')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_count_lines');
        Schema::dropIfExists('stock_counts');
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('stock_lots');
        Schema::dropIfExists('batches');
        Schema::dropIfExists('locations');
    }
};
