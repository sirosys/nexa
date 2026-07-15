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
        Schema::table('packages', function (Blueprint $table) {
            $table->foreignId('plan_id')->nullable()->after('is_starter')
                ->constrained('plans')->restrictOnDelete();
            // Snapshot harga plan untuk paket ini (bisa didiskon promo,
            // independen dari plans.price yang bisa berubah kapan saja) —
            // pola sama package_product.price vs products.price.
            $table->decimal('plan_price', 12, 2)->nullable()->after('plan_id');
            // Menggantikan duration_months lama — jumlah bulan plan yang
            // dibeli di paket ini, input manual staff (bukan lagi diturunkan
            // dari quantity produk bertipe 'langganan' yang dibundel).
            $table->unsignedSmallInteger('plan_qty')->nullable()->after('plan_price');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            $table->dropColumn(['plan_price', 'plan_qty']);
            $table->dropConstrainedForeignId('plan_id');
        });
    }
};
