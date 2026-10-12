<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 30)->default('cashier')->after('email');
            $table->boolean('is_active')->default(true)->after('role');
            $table->string('pin_code', 10)->nullable()->after('is_active');
            $table->string('phone', 30)->nullable()->after('pin_code');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['role', 'is_active', 'pin_code', 'phone']);
        });
    }
};
