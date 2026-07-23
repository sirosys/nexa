<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Pengingat SLA Tiket
    |--------------------------------------------------------------------------
    |
    | Berapa jam sejak service_tickets.claimed_at sebelum sebuah tiket
    | kategori teknis yang masih in_progress dikirimi pengingat SLA ke
    | teknisi yang menanganinya (lihat App\Console\Commands\TicketsSendSlaReminders
    | dan CLAUDE.md "Ticketing"). Cuma sekali kirim per tiket (kolom
    | service_tickets.sla_reminder_sent_at) — bukan H-3/H-1 bertingkat
    | seperti Renewal, cakupan v1 sengaja single-tier.
    |
    */

    'sla_reminder_hours' => (int) env('TICKET_SLA_REMINDER_HOURS', 24),

];
