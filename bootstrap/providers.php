<?php

use App\Providers\AppServiceProvider;
use App\Providers\Filament\AdminPanelProvider;
use Spatie\Activitylog\ActivitylogServiceProvider;

return [
    AppServiceProvider::class,
    AdminPanelProvider::class,
    ActivitylogServiceProvider::class,
];
