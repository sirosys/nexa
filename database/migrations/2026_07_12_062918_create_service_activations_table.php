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
        Schema::create('service_activations', function (Blueprint $table) {
            $table->id();
            // Satu service maksimal satu baris activation di iterasi ini —
            // konsisten pola receipts.service_order_id (tidak ada
            // retry/reassignment, lihat CLAUDE.md "Installation").
            $table->foreignId('service_id')->constrained('services')->restrictOnDelete()->unique();
            // Rename dari "sales_id" di draft ERD, konsisten singular FK
            // di seluruh project (mis. receipts.service_order_id).
            $table->foreignId('service_order_id')->constrained('service_orders')->restrictOnDelete();
            $table->foreignId('installer_id')->nullable()->constrained('users')->nullOnDelete();
            // Null berarti teknisi klaim sendiri (bukan ditugaskan staff).
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('claimed_at')->nullable();
            $table->string('odp_port')->nullable();
            $table->decimal('cable_length', 6, 1)->nullable()->comment('Panjang kabel (meter)');
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
        Schema::dropIfExists('service_activations');
    }
};
