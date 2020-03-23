<?php

namespace Padosoft\Laravel\PermissionExtended;

use Illuminate\Support\ServiceProvider;

class PermissionExtendedServiceProvider extends ServiceProvider
{
    public function boot()
    {
        if (function_exists('config_path')) { // function not available and 'publish' not relevant in Lumen
            $this->publishes([
                __DIR__ . '/../config/permission-extended.php' => config_path('permission-extended.php'),
            ], 'config');
        }

    }

    public function register()
    {

        $this->mergeConfigFrom(
            __DIR__ . '/../config/permission-extended.php',
            'permission-extended'
        );


    }

}
