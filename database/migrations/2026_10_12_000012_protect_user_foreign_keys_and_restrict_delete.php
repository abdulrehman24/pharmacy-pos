<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            Schema::table('sales', function (Blueprint $table) {
                $table->dropForeign(['cashier_id']);
                $table->foreign('cashier_id')->references('id')->on('users')->restrictOnDelete();
            });

            Schema::table('sales_returns', function (Blueprint $table) {
                $table->dropForeign(['cashier_id']);
                $table->foreign('cashier_id')->references('id')->on('users')->restrictOnDelete();
            });

            Schema::table('stock_counts', function (Blueprint $table) {
                $table->dropForeign(['conducted_by']);
                $table->foreign('conducted_by')->references('id')->on('users')->restrictOnDelete();
            });

            Schema::table('dispensing_records', function (Blueprint $table) {
                $table->dropForeign(['pharmacist_id']);
                $table->foreign('pharmacist_id')->references('id')->on('users')->restrictOnDelete();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            Schema::table('sales', function (Blueprint $table) {
                $table->dropForeign(['cashier_id']);
                $table->foreign('cashier_id')->references('id')->on('users')->cascadeOnDelete();
            });

            Schema::table('sales_returns', function (Blueprint $table) {
                $table->dropForeign(['cashier_id']);
                $table->foreign('cashier_id')->references('id')->on('users')->cascadeOnDelete();
            });

            Schema::table('stock_counts', function (Blueprint $table) {
                $table->dropForeign(['conducted_by']);
                $table->foreign('conducted_by')->references('id')->on('users')->cascadeOnDelete();
            });

            Schema::table('dispensing_records', function (Blueprint $table) {
                $table->dropForeign(['pharmacist_id']);
                $table->foreign('pharmacist_id')->references('id')->on('users')->cascadeOnDelete();
            });
        }
    }
};
