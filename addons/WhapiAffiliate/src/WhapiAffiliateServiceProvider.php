<?php

namespace Addons\WhapiAffiliate;

use App\Yantrana\Components\Subscription\Models\ManualSubscriptionModel;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class WhapiAffiliateServiceProvider extends ServiceProvider
{
    public function register(): void
    {
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
