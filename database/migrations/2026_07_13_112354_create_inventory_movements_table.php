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
        Schema::create('inventory_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_item_id')->constrained('inventory_items')->restrictOnDelete();
            // Cuma terisi untuk item is_serialized=true — satu movement
            // per unit (quantity selalu ±1 di kasus ini).
            $table->foreignId('inventory_unit_id')->nullable()->constrained('inventory_units')->nullOnDelete();
            // Label kategori untuk pelaporan/filter saja — arah
            // penambahan/pengurangan stok ditentukan TANDA `quantity`
            // (positif/negatif), bukan dari kolom ini, supaya tidak ada
            // sub-tipe adjustment-in/adjustment-out yang membingungkan.
            $table->string('type');
            $table->integer('quantity');
            // Terisi kalau movement ini dipicu konsumsi instalasi (type
            // 'out' dari InstallationService::complete()).
            $table->foreignId('service_id')->nullable()->constrained('services')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            // Ledger append-only — tidak pernah diedit setelah dibuat,
            // tapi tetap pakai timestamps() standar (bukan cuma
            // created_at) konsisten konvensi project, updated_at murni
            // tidak pernah tersentuh dalam praktiknya.
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_movements');
    }
};
