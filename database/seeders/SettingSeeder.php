<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    /**
     * Katalog setting — metadata (type/group/label/description) selalu
     * disinkronkan ulang dari sini tiap kali seeder jalan, TAPI nilai
     * (`value`) baris yang sudah ada dipertahankan (staff yang mengubahnya
     * lewat /settings tidak boleh tertimpa balik ke default config saat
     * migration/seeder dijalankan ulang) — cuma dipakai sebagai nilai awal
     * kalau baris belum pernah ada. Lihat CLAUDE.md "System Setting".
     */
    private const SETTINGS = [
        [
            'key' => 'billing.invoice_ttl_days',
            'type' => 'integer',
            'group' => 'billing',
            'label' => 'Masa Berlaku Tagihan Pendaftaran (hari)',
            'description' => 'Jumlah hari sebelum tagihan pendaftaran (Sale) otomatis dibatalkan kalau belum dibayar.',
        ],
        [
            'key' => 'renewal.remind_days_before.invoice',
            'type' => 'integer',
            'group' => 'renewal',
            'label' => 'Buat Tagihan Perpanjangan — H- sebelum expired',
            'description' => 'Berapa hari sebelum layanan expired, tagihan perpanjangan otomatis dibuat (sekaligus jadi reminder pertama).',
        ],
        [
            'key' => 'renewal.remind_days_before.h3',
            'type' => 'integer',
            'group' => 'renewal',
            'label' => 'Reminder Kedua — H- sebelum expired',
            'description' => 'Berapa hari sebelum layanan expired, reminder WhatsApp kedua dikirim kalau tagihan perpanjangan masih belum dibayar.',
        ],
        [
            'key' => 'renewal.remind_days_before.h1',
            'type' => 'integer',
            'group' => 'renewal',
            'label' => 'Reminder Terakhir — H- sebelum expired',
            'description' => 'Berapa hari sebelum layanan expired, reminder WhatsApp terakhir dikirim kalau tagihan perpanjangan masih belum dibayar.',
        ],
        [
            'key' => 'dismantle.suspended_months_threshold',
            'type' => 'integer',
            'group' => 'dismantle',
            'label' => 'Ambang Auto-Antre Dismantle (bulan)',
            'description' => 'Berapa bulan sejak layanan disuspend sebelum otomatis diantrekan untuk dibongkar.',
        ],
    ];

    public function run(): void
    {
        foreach (self::SETTINGS as $definition) {
            $existing = Setting::query()->where('key', $definition['key'])->first();

            Setting::query()->updateOrCreate(
                ['key' => $definition['key']],
                [
                    'type' => $definition['type'],
                    'group' => $definition['group'],
                    'label' => $definition['label'],
                    'description' => $definition['description'],
                    'value' => $existing->value ?? (string) config($definition['key']),
                ]
            );
        }
    }
}
