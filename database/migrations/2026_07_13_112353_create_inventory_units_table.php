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
        Schema::create('inventory_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_item_id')->constrained('inventory_items')->restrictOnDelete();
            $table->string('serial_number')->unique();
            // in_stock -> installed (dipasang ke sebuah Service, lewat
            // InstallationService::complete()). BELUM ada jalur balik
            // otomatis saat Service di-dismantle (unit tidak kembali jadi
            // stok otomatis) — gap terdokumentasi, lihat CLAUDE.md
            // "Inventaris".
            $table->string('status')->default('in_stock');
            // Terisi begitu unit dipasang lewat InstallationService::complete().
            $table->foreignId('service_id')->nullable()->constrained('services')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_units');
    }
};
