<?php

use App\Providers\AppServiceProvider;
use App\Providers\MikrotikServiceProvider;
use App\Providers\WhatsappServiceProvider;
use App\Providers\XenditServiceProvider;

return [
    AppServiceProvider::class,
    WhatsappServiceProvider::class,
    XenditServiceProvider::class,
    MikrotikServiceProvider::class,
];
