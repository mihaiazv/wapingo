<?php

namespace Addons\WhapiAffiliate;

use Addons\WhapiAffiliate\Decorators\ManualSubscriptionEngineDecorator;
use Addons\WhapiAffiliate\Decorators\SubscriptionEngineDecorator;
use Addons\WhapiAffiliate\Services\AffiliateTracker;
use App\Yantrana\Base\BaseMailer;
use App\Yantrana\Components\Auth\Repositories\AuthRepository;
use App\Yantrana\Components\Dashboard\DashboardEngine;
use App\Yantrana\Components\Subscription\ManualSubscriptionEngine as BaseManualSubscriptionEngine;
use App\Yantrana\Components\Subscription\Models\ManualSubscriptionModel;
use App\Yantrana\Components\Subscription\PaymentEngines\PaystackEngine;
use App\Yantrana\Components\Subscription\PaymentEngines\PaypalEngine;
use App\Yantrana\Components\Subscription\PaymentEngines\PhonePeEngine;
use App\Yantrana\Components\Subscription\PaymentEngines\RazorpayEngine;
use App\Yantrana\Components\Subscription\PaymentEngines\YoomoneyEngine;
use App\Yantrana\Components\Subscription\Repositories\ManualSubscriptionRepository;
use App\Yantrana\Components\Subscription\Repositories\SubscriptionRepository;
use App\Yantrana\Components\Subscription\SubscriptionEngine as BaseSubscriptionEngine;
use App\Yantrana\Components\Vendor\Repositories\VendorRepository;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class WhapiAffiliateServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(AffiliateTracker::class, fn () => new AffiliateTracker());

        $this->app->bind(BaseSubscriptionEngine::class, function ($app) {
            return new SubscriptionEngineDecorator(
                $app->make(SubscriptionRepository::class),
                $app->make(VendorRepository::class),
                $app->make(ManualSubscriptionRepository::class),
                $app->make(DashboardEngine::class),
                $app->make(AffiliateTracker::class),
            );
        });

        $this->app->bind(BaseManualSubscriptionEngine::class, function ($app) {
            return new ManualSubscriptionEngineDecorator(
                $app->make(ManualSubscriptionRepository::class),
                $app->make(VendorRepository::class),
                $app->make(PaypalEngine::class),
                $app->make(AuthRepository::class),
                $app->make(BaseMailer::class),
                $app->make(DashboardEngine::class),
                $app->make(RazorpayEngine::class),
                $app->make(PaystackEngine::class),
                $app->make(YoomoneyEngine::class),
                $app->make(BaseSubscriptionEngine::class),
                $app->make(PhonePeEngine::class),
                $app->make(AffiliateTracker::class),
            );
        });

        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'WhapiAffiliate');
    }

    public function boot(): void
    {
        View::composer('payment-success', function ($view) {
            $txnReferenceId = $view->txnReferenceId ?? null;

            if (empty($txnReferenceId)) {
                return;
            }

            $manualSubscription = ManualSubscriptionModel::query()
                ->where('__data->manual_txn_details->txn_reference', $txnReferenceId)
                ->orderByDesc('created_at')
                ->first();

            if (!$manualSubscription) {
                return;
            }

            $currency = getAppSettings('currency') ?: 'USD';

            $customData = [
                'subscription_id' => $manualSubscription->_id,
                'subscription_uid' => $manualSubscription->_uid,
                'plan_id' => $manualSubscription->plan_id,
                'vendor_id' => $manualSubscription->vendors__id,
                'charges_frequency' => $manualSubscription->charges_frequency,
                'transaction_reference' => $txnReferenceId,
                'manual_txn_details' => data_get($manualSubscription->__data, 'manual_txn_details'),
                'prepared_plan_details' => data_get($manualSubscription->__data, 'prepared_plan_details'),
            ];

            $view->getFactory()->startPush('footer', view('WhapiAffiliate::tracking', [
                'orderId' => $manualSubscription->_uid,
                'orderAmount' => number_format((float) $manualSubscription->charges, 2, '.', ''),
                'orderCurrency' => $currency,
                'orderStatus' => $manualSubscription->status ?? 'success',
                'orderTracking' => data_get($manualSubscription->__data, 'manual_txn_details.selected_payment_method', 'manual'),
                'customData' => $customData,
            ])->render());
        });
    }
}
