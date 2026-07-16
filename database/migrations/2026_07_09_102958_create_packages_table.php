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
        Schema::create('packages', function (Blueprint $table) {
            $table->id();
            $table->string('code')->nullable()->unique();
            $table->boolean('is_starter')->default(false);
            // null = unlimited (paket selalu tersedia dipilih selama masih
            // is_starter). Terisi = paket otomatis tidak lagi ditawarkan
            // untuk pendaftaran baru begitu tanggal ini lewat — lihat
            // Package::isAvailable()/scopeAvailable().
            $table->timestamp('valid_until')->nullable();
            // Plan (tier layanan internet) wajib untuk semua paket — lihat
            // CLAUDE.md "Plan"/"Product & Package". Nullable di DB murni
            // supaya migration bisa dijalankan ke tabel berisi data;
            // "wajib" ditegakkan di PackageRequest.
            $table->foreignId('plan_id')->nullable()->constrained('plans')->restrictOnDelete();
            // Jumlah bulan Plan yang dibeli di paket ini, input manual staff.
            $table->unsignedSmallInteger('plan_qty')->nullable();
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('price', 12, 2);
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
        Schema::dropIfExists('packages');
    }
};
