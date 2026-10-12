<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchases', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number', 30)->unique();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('supplier_invoice_number', 50)->nullable();
            $table->date('invoice_date');
            $table->bigInteger('subtotal_paisa')->default(0);
            $table->bigInteger('discount_paisa')->default(0);
            $table->bigInteger('tax_paisa')->default(0);
            $table->bigInteger('total_paisa')->default(0);
            $table->bigInteger('paid_paisa')->default(0);
            $table->string('payment_method', 30)->default('cash');
            $table->string('status', 20)->default('draft'); // draft, posted, cancelled
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['supplier_id', 'status', 'invoice_date']);
        });

        Schema::create('purchase_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_id')->constrained()->cascadeOnDelete();
            $table->foreignId('medicine_id')->constrained()->cascadeOnDelete();
            $table->foreignId('medicine_package_id')->nullable()->constrained()->nullOnDelete();
            $table->string('batch_number', 50);
            $table->date('expiry_date');
            $table->unsignedInteger('package_quantity');
            $table->unsignedInteger('bonus_base_quantity')->default(0);
            $table->unsignedInteger('total_base_quantity');
            $table->bigInteger('package_cost_paisa');
            $table->decimal('unit_cost_paisa', 14, 4); // Unit cost per base item taking bonus into account
            $table->bigInteger('total_cost_paisa');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_lines');
        Schema::dropIfExists('purchases');
    }
};
