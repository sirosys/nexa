<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Distamp sekali oleh ServiceTicketService::sendSlaReminder() —
     * cuma relevan untuk tiket kategori teknis yang sudah in_progress
     * (lihat ServiceTicket::CATEGORIES_REQUIRING_TECHNICIAN), tidak pernah
     * di-reset (tidak ada alur reassignment di app ini, jadi satu siklus
     * hidup tiket = maksimal satu kali reminder). Lihat CLAUDE.md
     * "Ticketing".
     */
    public function up(): void
    {
        Schema::table('service_tickets', function (Blueprint $table) {
            $table->timestamp('sla_reminder_sent_at')->nullable()->after('claimed_at');
        });
    }

    public function down(): void
    {
        Schema::table('service_tickets', function (Blueprint $table) {
            $table->dropColumn('sla_reminder_sent_at');
        });
    }
};
