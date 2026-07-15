<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->foreignId('plan_id')->nullable()->after('package_id')
                ->constrained('plans')->restrictOnDelete();
            // Snapshot independen dari packages.plan_price — untuk Sale
            // registrasi disalin dari package saat itu, untuk Sale renewal
            // diisi harga katalog plans.price SAAT INI (lihat RenewalService).
            $table->decimal('plan_price', 12, 2)->nullable()->after('plan_id');
            // Jumlah bulan yang ditagih untuk plan di Sale ini — selalu 1
            // untuk renewal otomatis, dibaca RenewalService::reactivate()
            // untuk menghitung perpanjangan expired_at.
            $table->unsignedSmallInteger('plan_qty')->nullable()->after('plan_price');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn(['plan_price', 'plan_qty']);
            $table->dropConstrainedForeignId('plan_id');
        });
    }
};
