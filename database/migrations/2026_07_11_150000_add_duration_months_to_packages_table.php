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
            // 1 = fallback saat paket tidak punya item produk bertipe
            // 'langganan' sama sekali (lihat PackageService::deriveDurationMonths()).
            $table->unsignedSmallInteger('duration_months')->default(1)->after('is_starter');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            $table->dropColumn('duration_months');
        });
    }
};
