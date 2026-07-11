<?php

namespace Tests;

use App\Services\Billing\XenditGateway;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Tests\Support\FakeXenditGateway;

abstract class TestCase extends BaseTestCase
{
    /**
     * Binding default untuk semua test: mencegah panggilan HTTP sungguhan
     * ke Xendit setiap kali sebuah Service (dan Sale pendaftarannya) dibuat
     * lewat ServiceService::create() — hampir semua test CRUD Service tidak
     * peduli soal Xendit sama sekali. Test Billing yang butuh perilaku
     * spesifik (gagal, status tertentu, dst) meng-override binding ini
     * sendiri lewat $this->app->instance(XenditGateway::class, ...).
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->app->instance(XenditGateway::class, new FakeXenditGateway);
    }
}
