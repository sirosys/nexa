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
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            // Disimpan sebagai string, di-cast ke tipe asli sesuai kolom
            // `type` lewat Setting::get() — satu tabel generik menampung
            // aturan bisnis bertipe apa pun tanpa migration baru per key.
            $table->text('value')->nullable();
            $table->string('type')->default('string');
            // Untuk pengelompokan tampilan di halaman /settings (mis.
            // 'billing', 'renewal', 'dismantle') — bukan FK, murni label.
            $table->string('group');
            $table->string('label');
            $table->text('description')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
