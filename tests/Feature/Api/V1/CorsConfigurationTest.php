<?php

namespace Tests\Feature\Api\V1;

use Illuminate\Support\Facades\Config;
use Tests\TestCase;

/**
 * config/cors.php sengaja tidak ada sama sekali di app ini sampai gap ini
 * ditutup (lihat CLAUDE.md "API Customer-Facing") — HandleCors bawaan
 * Laravel sudah aktif di global middleware stack, tapi no-op total tanpa
 * file config ini (allowed_origins selalu kosong). Test ini membuktikan
 * wiring-nya benar-benar berlaku, bukan cuma filenya ada.
 */
class CorsConfigurationTest extends TestCase
{
    public function test_cors_headers_present_for_configured_allowed_origin(): void
    {
        Config::set('cors.allowed_origins', ['https://app.example.test']);

        $response = $this->withHeaders([
            'Origin' => 'https://app.example.test',
        ])->getJson('/api/v1/services');

        $response->assertHeader('Access-Control-Allow-Origin', 'https://app.example.test');
    }

    /**
     * fruitcake/php-cors (dipakai HandleCors bawaan Laravel) punya jalur
     * khusus kalau `allowed_origins` cuma berisi SATU entri — origin itu
     * selalu dipasang ke header tanpa mencocokkan Origin request (optimasi
     * "single origin" library-nya). Supaya benar-benar menguji pencocokan
     * origin (bukan jalur single-origin itu), config di sini sengaja diisi
     * DUA origin supaya CorsService::isOriginAllowed() yang jalan.
     */
    public function test_cors_headers_absent_for_unconfigured_origin(): void
    {
        Config::set('cors.allowed_origins', ['https://app.example.test', 'https://other.example.test']);

        $response = $this->withHeaders([
            'Origin' => 'https://evil.example.test',
        ])->getJson('/api/v1/services');

        $response->assertHeaderMissing('Access-Control-Allow-Origin');
    }
}
