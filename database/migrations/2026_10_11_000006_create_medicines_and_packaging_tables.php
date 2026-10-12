<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('manufacturers', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('contact_info')->nullable();
            $table->timestamps();
        });

        Schema::create('medicines', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('brand_name')->index();
            $table->string('generic_name')->nullable()->index();
            $table->string('strength', 50)->nullable(); // e.g. 500mg, 10mg/5ml
            $table->string('dosage_form', 50)->default('Tablet'); // Tablet, Capsule, Syrup, Injection, Drops, Cream, Suspension, Inhaler, Sachet
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('manufacturer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('base_unit', 30)->default('Tablet'); // Tablet, Piece, Capsule, ml, gm, Vial, Bottle
            $table->string('rack_shelf', 50)->nullable();
            $table->unsignedInteger('reorder_level')->default(10); // in base unit
            $table->boolean('prescription_required')->default(false);
            $table->boolean('is_narcotic')->default(false); // Controlled medicine
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['brand_name', 'generic_name']);
        });

        Schema::create('medicine_packages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('medicine_id')->constrained()->cascadeOnDelete();
            $table->string('name', 50); // e.g. Box of 100, Strip of 10, Piece / Tablet
            $table->unsignedInteger('base_quantity')->default(1); // 1 for base, 10 for strip, 100 for box
            $table->bigInteger('default_sell_price_paisa')->default(0);
            $table->bigInteger('default_purchase_price_paisa')->default(0);
            $table->boolean('is_default_sale')->default(false);
            $table->boolean('is_default_purchase')->default(false);
            $table->timestamps();

            $table->unique(['medicine_id', 'name']);
        });

        Schema::create('medicine_barcodes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('medicine_id')->constrained()->cascadeOnDelete();
            $table->foreignId('medicine_package_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('barcode', 100)->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('medicine_barcodes');
        Schema::dropIfExists('medicine_packages');
        Schema::dropIfExists('medicines');
        Schema::dropIfExists('manufacturers');
        Schema::dropIfExists('categories');
    }
};
