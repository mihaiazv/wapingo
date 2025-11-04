<?php

namespace Addons\VendorTemplatesApi;

use Illuminate\Support\ServiceProvider;

class VendorTemplatesApiServiceProvider extends ServiceProvider
{
    public function register()
    {
        //
    }

    public function boot()
    {
        $routesPath = __DIR__ . '/routes/api.php';
        if (file_exists($routesPath)) {
            $this->loadRoutesFrom($routesPath);
        }
    }
}
