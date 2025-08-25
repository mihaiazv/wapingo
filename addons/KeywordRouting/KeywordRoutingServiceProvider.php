<?php
namespace Addons\KeywordRouting;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;

class KeywordRoutingServiceProvider extends ServiceProvider
{
    public function boot()
    {
        
       
        
        
        $routesPath = __DIR__ . '/routes.php';
        if (file_exists($routesPath)) {
            $this->loadRoutesFrom($routesPath);
        }

        $this->loadViewsFrom(__DIR__ . '/views', 'KeywordRouting');

        if (is_dir(__DIR__.'/Migrations')) {
            $this->loadMigrationsFrom(__DIR__.'/Migrations');
        }

        $listenersPath = __DIR__ . '/listeners.php';
        if (file_exists($listenersPath)) {
            include_once($listenersPath);
        }
    }

    public function register()
    {
        // Extra servicii/bindings dacă ai nevoie
    }
}
