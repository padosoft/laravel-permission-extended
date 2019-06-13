<?php

namespace Padosoft\Laravel\PermissionExtended;

use Illuminate\Support\ServiceProvider;

class PermissionExtendedServiceProvider extends ServiceProvider
{
    public function boot()
    {
        if (isNotLumen()) {
            $this->publishes([
                __DIR__ . '/../config/permission-extended.php' => config_path('permission-extended.php'),
            ], 'config');
        }

    }

    public function register()
    {
        if (isNotLumen()) {
            $this->mergeConfigFrom(
                __DIR__ . '/../config/permission-extended.php',
                'permission-extended'
            );
        }


    }

}
