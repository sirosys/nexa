<?php

namespace App\Providers;

use App\Services\Billing\Drivers\HttpXenditGateway;
use App\Services\Billing\XenditGateway;
use Illuminate\Support\ServiceProvider;

class XenditServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(XenditGateway::class, fn () => new HttpXenditGateway(
            config('services.xendit.base_url'),
            config('services.xendit.secret_key'),
        ));
    }
}
