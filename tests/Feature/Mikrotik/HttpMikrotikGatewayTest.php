<?php

namespace Tests\Feature\Mikrotik;

use App\Models\Pop;
use App\Services\Mikrotik\Drivers\HttpMikrotikGateway;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

/**
 * Verifikasi bentuk request yang dikirim HttpMikrotikGateway lewat
 * Http::fake() — BUKAN bukti driver ini benar terhadap RouterOS
 * sungguhan (belum pernah diuji ke router asli, lihat CLAUDE.md
 * "Integrasi MikroTik"), cuma memastikan URL/payload/auth sesuai
 * dokumentasi resmi yang jadi rujukan saat driver ini ditulis.
 */
class HttpMikrotikGatewayTest extends TestCase
{
    private function pop(): Pop
    {
        $pop = new Pop([
            'code' => 'POP000001',
            'host' => '172.16.0.1',
            'api_port' => 443,
            'api_username' => 'api',
            'token' => 'rahasia',
        ]);
        $pop->exists = true;
        $pop->id = 1;

        return $pop;
    }

    public function test_create_pppoe_secret_sends_put_with_basic_auth(): void
    {
        Http::fake(['*' => Http::response([], 200)]);

        (new HttpMikrotikGateway)->createPppoeSecret($this->pop(), 'SRV000001', '123456', 'default-profile');

        Http::assertSent(function ($request) {
            return $request->url() === 'http://172.16.0.1:443/rest/ppp/secret'
                && $request->method() === 'PUT'
                && $request['name'] === 'SRV000001'
                && $request['password'] === '123456'
                && $request['service'] === 'pppoe'
                && $request['profile'] === 'default-profile'
                && $request->hasHeader('Authorization');
        });
    }

    public function test_create_pppoe_secret_throws_on_failure(): void
    {
        Http::fake(['*' => Http::response(['detail' => 'invalid'], 400)]);

        $this->expectException(RuntimeException::class);

        (new HttpMikrotikGateway)->createPppoeSecret($this->pop(), 'SRV000001', '123456');
    }

    public function test_enable_and_disable_patch_using_resolved_id(): void
    {
        Http::fake([
            '*/rest/ppp/secret?*' => Http::response([['.id' => '*7', 'name' => 'SRV000001']], 200),
            '*/rest/ppp/secret/*7' => Http::response([], 200),
        ]);

        (new HttpMikrotikGateway)->disablePppoeSecret($this->pop(), 'SRV000001');

        Http::assertSent(function ($request) {
            return $request->method() === 'PATCH'
                && str_ends_with($request->url(), '/rest/ppp/secret/*7')
                && $request['disabled'] === 'true';
        });
    }

    public function test_delete_is_idempotent_when_secret_already_gone(): void
    {
        Http::fake(['*/rest/ppp/secret?*' => Http::response([], 200)]);

        $result = (new HttpMikrotikGateway)->deletePppoeSecret($this->pop(), 'SRV000001');

        $this->assertTrue($result);
        Http::assertNotSent(fn ($request) => $request->method() === 'DELETE');
    }

    public function test_throws_when_pop_has_no_host_configured(): void
    {
        $pop = new Pop(['code' => 'POP000002']);
        $pop->exists = true;
        $pop->id = 2;

        $this->expectException(RuntimeException::class);

        (new HttpMikrotikGateway)->createPppoeSecret($pop, 'SRV000002', '123456');
    }
}
