<?php

use Illuminate\Support\Facades\Route;
use App\Http\Middleware\VendorAccessCheckpost;
use Addons\ContactStripePaymentLinks\Yantrana\Controllers\ContactStripePaymentLinksController;
use App\Http\Middleware\CentralAccessCheckpost;

Route::middleware([
    'web',
])->group(function () {
    Route::middleware([
        VendorAccessCheckpost::class,
    ])->prefix('vendor-console/addon-settings/contact-stripe-payment-links')
        ->group(function () {
            // payment link settings
            Route::get('/settings', [
                ContactStripePaymentLinksController::class,
                'showSettings'
            ])->name('addon.contact_payment_links_stripe.vendor.settings.read');
            // create payment link
            Route::post('/create-payment-link', [
                ContactStripePaymentLinksController::class,
                'createPaymentLink'
            ])->name('addon.contact_payment_links_stripe.vendor.payment_link.create');
            // create and send payment link
            Route::post('/create-and-send', [
                ContactStripePaymentLinksController::class,
                'createAndSendPaymentLink'
            ])->name('addon.contact_payment_links_stripe.vendor.payment_link.write');
            Route::post('/test-payment-complete-template', [
                ContactStripePaymentLinksController::class,
                'testPaymentCompleteTemplate'
            ])->name('addon.contact_payment_links_stripe.vendor.test_pc_template.write');
        });
        Route::middleware([
            CentralAccessCheckpost::class,
            ])->prefix('/addons/ContactStripePaymentLinks')
            ->group(function () {
                // server the assets
                Route::get('/assets/{path}', [
                    ContactStripePaymentLinksController::class,
                    'assetServe'
                ])->name('addon.ContactStripePaymentLinks.assets');

                Route::get('/setup', [
                    ContactStripePaymentLinksController::class,
                    'setupView'
                ])->name('addon.ContactStripePaymentLinks.setup_view');

                Route::post('/process-activation', [
                    ContactStripePaymentLinksController::class,
                    'processAddonActivation'
                ])->name('addon.ContactStripePaymentLinks.processAddonActivation');

                Route::post('/process-deactivation', [
                    ContactStripePaymentLinksController::class,
                    'processAddonDeactivation'
                ])->name('addon.ContactStripePaymentLinks.processAddonDeactivation');
        });
});
Route::get('/addon-contact-stripe-payment-links-remove-process-remote', [
    ContactStripePaymentLinksController::class,
    'processAddonDeactivation',
])->name('addon.ContactStripePaymentLinks.processAddonDeactivation_remote');
// Stripe Payment webhook
Route::post('/stripe-contact-payment-webhook/{vendorUid}', [
    ContactStripePaymentLinksController::class, 'handleStripeWebhook'
])->name('addon.contact_payment_links_stripe.vendor.payment_webhook.write');