<?php

use App\Providers\AppServiceProvider;
use App\Providers\TenancyServiceProvider;
use Modules\Demo\Providers\DemoServiceProvider;

return [
    AppServiceProvider::class,
    TenancyServiceProvider::class,
    DemoServiceProvider::class,
];
