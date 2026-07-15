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
            $table->dropConstrainedForeignId('base_product_id');
            $table->dropColumn('duration_months');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            $table->unsignedSmallInteger('duration_months')->default(1)->after('is_starter');
            $table->foreignId('base_product_id')->nullable()->after('plan_qty')
                ->constrained('products')->restrictOnDelete();
        });
    }
};
