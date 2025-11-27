<?php

return [
    // SystemDatabaseServiceProvider must load early to override .env settings
    App\Providers\SystemDatabaseServiceProvider::class,
    App\Providers\AppServiceProvider::class,
    App\Providers\CorsServiceProvider::class,
    App\Providers\Filament\AdminPanelProvider::class,
    App\Providers\FortifyServiceProvider::class,
    App\Providers\VoltServiceProvider::class,
];
