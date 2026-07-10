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
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->string('code')->nullable()->unique();
            $table->foreignId('service_id')->constrained('services')->restrictOnDelete();
            $table->foreignId('package_id')->constrained('packages')->restrictOnDelete();
            // Snapshot dari packages.is_starter milik package_id yang sedang
            // dipilih — dihitung ulang tiap create/update, lihat CLAUDE.md "Sales".
            $table->boolean('is_starter')->default(false);
            $table->decimal('total', 12, 2)->default(0);
            // Turunan SUM(sale_products.discount), bukan input manual staff.
            $table->decimal('discount', 12, 2)->default(0);
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('tax', 12, 2)->default(0);
            $table->decimal('admin_fee', 12, 2)->default(0);
            $table->decimal('grandtotal', 12, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamp('invoiced_at')->nullable();
            $table->timestamp('expired_at')->nullable();
            $table->timestamp('settled_at')->nullable();
            $table->timestamp('canceled_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sales');
    }
};
