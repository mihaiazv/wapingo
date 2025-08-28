<?php

/**
 * Contact Stripe Payment Links Sent for WhatsJet Addon
 * by livelyworks
 */

namespace Addons\ContactStripePaymentLinks;

use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class ContactStripePaymentLinksServiceProvider extends ServiceProvider
{
    public function register()
    {
        // Merge configuration
        $this->mergeConfigFrom(
            __DIR__ . '/../config/lwSystem.php',
            'lwSystem-ContactStripePaymentLinks'
        );
        // translation source folders
        $this->mergeConfigFrom(
            __DIR__ . '/../config/translation-source-folders.php',
            '__misc.translation_source_folders'
        );
        // app settings items
        $this->mergeConfigFrom(
            __DIR__ . '/../config/addon-app-settings.php',
            '__settings.items'
        );
        // addon settings
        $this->mergeConfigFrom(
            __DIR__ . '/../config/lw-addon-cpl-stripe.php',
            'contact-stripe-payment-links'
        );
        // addon vendor settings
        $this->mergeConfigFrom(
            __DIR__ . '/../config/addon-vendor-settings.php',
            '__vendor-settings.items'
        );
    }

    public function boot()
    {
        if (swaksharyipadtalniforadditionals('ContactStripePaymentLinks')) {
            // append to plans
            $planConfiguration = [
                'type' => 'switch', // on or off
                'description' => __tr('Send Stripe Payment Links to Contacts'),
                'limit' => 1, // 0 for none, 1 for enable
            ];
            config([
                'lw-plans.free.features.ContactStripePaymentLinks' => $planConfiguration
            ]);
            foreach (config('lw-plans.paid') as $paidPlanKey => $paidPlan) {
                config([
                    "lw-plans.paid.$paidPlanKey.features.ContactStripePaymentLinks" => $planConfiguration
                ]);
            }
            View::composer('whatsapp.chat', function ($view) {
                $vendorPlanDetails = vendorPlanDetails('ContactStripePaymentLinks', 0, getVendorId());
                if($vendorPlanDetails['is_limit_available']) {
                    // Push content to the stack
                    $view->getFactory()->startPush('chatRightSidebarFooter', view('ContactStripePaymentLinks::index')->render());
                }
            });
            View::composer('layouts.navbars.sidebar', function ($view) {
                $vendorPlanDetails = vendorPlanDetails('ContactStripePaymentLinks', 0, getVendorId());
                if($vendorPlanDetails['is_limit_available']) {
                    // Push content to the stack
                    $view->getFactory()->startPush('vendorSidebarSettingsLinks', view('ContactStripePaymentLinks::sidebar')->render());
                }
            });
        }
        // Load views
        $this->loadViewsFrom(
            __DIR__ . '/../resources/views',
            'ContactStripePaymentLinks'
        );
        // Load migrations
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        // Load routes
        $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');
        // Publish resources
        $this->publishes([
            __DIR__ . '/../config/lw-addon-cpl-stripe.php' => config_path('lw-addon-cpl-stripe.php'),
            __DIR__ . '/../resources/views' => resource_path('views/vendor/ContactStripePaymentLinks'),
        ], 'ContactStripePaymentLinks');
    }
}
