<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('counters', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name', 50);
            $table->string('ip_address', 45)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('counter_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->bigInteger('opening_float_paisa')->default(0);
            $table->timestamp('opened_at')->useCurrent();
            $table->timestamp('closed_at')->nullable();
            $table->bigInteger('counted_cash_paisa')->nullable();
            $table->bigInteger('expected_cash_paisa')->nullable();
            $table->bigInteger('variance_paisa')->nullable();
            $table->text('notes')->nullable();
            $table->string('status', 20)->default('open'); // open, closed
            $table->timestamps();

            $table->index(['counter_id', 'status']);
            $table->index(['user_id', 'status']);
        });

        Schema::create('cash_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shift_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20); // cash_in, cash_out
            $table->bigInteger('amount_paisa');
            $table->string('reason');
            $table->timestamps();
        });

        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shift_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('category', 50); // utilities, tea_refreshment, maintenance, salary, supplies, other
            $table->bigInteger('amount_paisa');
            $table->string('description');
            $table->string('receipt_ref')->nullable();
            $table->timestamps();
        });

        Schema::create('printer_profiles', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50);
            $table->string('connection_type', 20)->default('browser'); // browser, network, windows_raw
            $table->string('host')->nullable(); // e.g. 192.168.1.200
            $table->integer('port')->default(9100);
            $table->unsignedSmallInteger('columns')->default(42); // 42 for 80mm, 32 for 58mm
            $table->unsignedSmallInteger('paper_width')->default(80); // 80 or 58
            $table->boolean('has_cutter')->default(true);
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });

        Schema::create('print_jobs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sale_id')->nullable()->index();
            $table->foreignId('printer_profile_id')->nullable()->constrained()->nullOnDelete();
            $table->string('job_type', 30)->default('receipt'); // receipt, test, duplicate
            $table->string('status', 20)->default('pending'); // pending, submitted, failed
            $table->text('error_message')->nullable();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('print_jobs');
        Schema::dropIfExists('printer_profiles');
        Schema::dropIfExists('expenses');
        Schema::dropIfExists('cash_movements');
        Schema::dropIfExists('shifts');
        Schema::dropIfExists('counters');
    }
};
