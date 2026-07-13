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
        Schema::create('service_tickets', function (Blueprint $table) {
            $table->id();
            $table->string('code')->nullable()->unique();
            // Beda dari service_activations/service_dismantles — satu
            // service bisa punya banyak tiket sepanjang riwayatnya, jadi
            // TIDAK unique. Tetap restrictOnDelete konsisten pola FK ke
            // services lain (sales.service_id, dst).
            $table->foreignId('service_id')->constrained('services')->restrictOnDelete();
            // Draft ERD tidak menggambarkan kolom ini sama sekali — kategori
            // & subject/description ditambahkan sendiri setelah dikonfirmasi
            // ke user, lihat CLAUDE.md "Ticketing". Fixed list via
            // Rule::in di ServiceTicketRequest, pola sama products.type.
            $table->string('category');
            $table->string('subject');
            $table->text('description');
            // Kolom status eksplisit (bukan dihitung dari kombinasi
            // timestamp), pola sama services.status. Default 'open'.
            $table->string('status')->default('open');
            // Hanya terisi untuk kategori yang butuh penanganan teknisi
            // (lihat ServiceTicket::CATEGORIES_REQUIRING_TECHNICIAN) — mirror
            // service_activations.installer_id/assigned_by/claimed_at.
            $table->foreignId('assigned_technician_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('claimed_at')->nullable();
            $table->text('resolution_notes')->nullable();
            // Nama solved_at/solved_by dipertahankan dari draft ERD apa
            // adanya (tidak menyesatkan seperti kasus residential/starter,
            // jadi tidak perlu di-rename).
            $table->timestamp('solved_at')->nullable();
            $table->foreignId('solved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            // Riwayat keluhan pelanggan — soft delete, pola sama
            // services/sales, bukan hard delete seperti master data.
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('service_tickets');
    }
};
