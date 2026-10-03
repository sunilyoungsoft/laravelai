<?php

use App\Providers\AppServiceProvider;
use App\Providers\TenancyServiceProvider;
use App\Providers\WorkspaceAuthServiceProvider;
use Modules\Demo\Providers\DemoServiceProvider;

return [
    AppServiceProvider::class,
    TenancyServiceProvider::class,
    WorkspaceAuthServiceProvider::class,
    DemoServiceProvider::class,
];
