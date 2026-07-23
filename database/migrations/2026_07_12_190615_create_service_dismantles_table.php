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
        Schema::create('service_dismantles', function (Blueprint $table) {
            $table->id();
            // Satu service maksimal satu baris dismantle di iterasi ini —
            // konsisten pola service_activations.service_id.
            $table->foreignId('service_id')->constrained('services')->restrictOnDelete()->unique();
            // FK ke activation, BUKAN ke service order — deviasi sadar dari pola
            // service_activations.service_order_id: dismantle adalah "kebalikan dari
            // sebuah instalasi", bukan terikat ke Order Layanan (service suspended
            // karena telat bayar bisa saja tidak punya Order Layanan renewal yang
            // settled sama sekali). Lihat CLAUDE.md "Dismantle".
            $table->foreignId('activation_id')->constrained('service_activations')->restrictOnDelete();
            // Nama "technician_id", bukan "installer_id" — semantiknya lebih
            // tepat untuk konteks pembongkaran (tidak ada yang "menginstal").
            $table->foreignId('technician_id')->nullable()->constrained('users')->nullOnDelete();
            // Null berarti teknisi klaim sendiri (bukan ditugaskan staff).
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('claimed_at')->nullable();
            // Dismantle punya DUA jalur masuk antrean (auto scheduler vs
            // manual staff) — beda dari Installation yang cuma satu jalur
            // (webhook Billing). Null = diantrekan otomatis scheduler,
            // terisi = staff yang memicu manual.
            $table->foreignId('queued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('photo')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('completed_at')->nullable();
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
        Schema::dropIfExists('service_dismantles');
    }
};
