<?php

namespace Addons\WhapiAffiliate\Decorators;

use Addons\WhapiAffiliate\Services\AffiliateTracker;
use App\Yantrana\Components\Dashboard\DashboardEngine;
use App\Yantrana\Components\Subscription\Repositories\ManualSubscriptionRepository;
use App\Yantrana\Components\Subscription\Repositories\SubscriptionRepository;
use App\Yantrana\Components\Subscription\SubscriptionEngine as BaseSubscriptionEngine;
use App\Yantrana\Components\Vendor\Repositories\VendorRepository;
use Illuminate\Support\Arr;
use Laravel\Cashier\Exceptions\IncompletePayment;

class SubscriptionEngineDecorator extends BaseSubscriptionEngine
{
    protected AffiliateTracker $affiliateTracker;

    public function __construct(
        SubscriptionRepository $subscriptionRepository,
        VendorRepository $vendorRepository,
        ManualSubscriptionRepository $manualSubscriptionRepository,
        DashboardEngine $dashboardEngine,
        AffiliateTracker $affiliateTracker
    ) {
        parent::__construct(
            $subscriptionRepository,
            $vendorRepository,
            $manualSubscriptionRepository,
            $dashboardEngine
        );

        $this->affiliateTracker = $affiliateTracker;
    }

    public function processCreate($request)
    {
        try {
            $planRequest = explode('___', $request->plan);
            $getPlanDetails = $this->getCurrentPlan($planRequest[0]);
            if (! isset($planRequest[1])) {
                setRedirectAlertMessage(__tr('Invalid Plan'), 'error');
                return redirect()->route('subscription.read.show');
            }
            $planPriceId = Arr::get($getPlanDetails, 'charges.' . $planRequest[1] . '.price_id');
            if (! $planPriceId) {
                setRedirectAlertMessage(__tr('Plan not available to subscribe'), 'error');
                return redirect()->route('subscription.read.show');
            }
            $checkPlanUsages = $this->dashboardEngine->checkPlanUsages($getPlanDetails, getVendorId());
            if ($checkPlanUsages) {
                setRedirectAlertMessage(__tr('Due to the over use of following features __checkPlanUsages__ as per the selected plan so this plan can not be subscribed as it has lower limits, Please choose different plan OR reduce your usages.', [
                    '__checkPlanUsages__' => "$checkPlanUsages",
                ]), 'error');
                return redirect()->route('subscription.read.show');
            }
            $trialDays = Arr::get($getPlanDetails, 'trial_days');
            $planId = Arr::get($getPlanDetails, 'id');
            $subscription = $this->subscriber()->newSubscription($planId, $planPriceId);
            if ($trialDays) {
                $subscription->trialDays($trialDays);
            }
            $createdSubscription = $subscription->allowPaymentFailures()->create($request->paymentMethod);

            $this->affiliateTracker->trackSubscription([
                'order_id' => $createdSubscription->stripe_id ?? $createdSubscription->id,
                'order_currency' => config('cashier.currency', 'USD'),
                'order_total' => Arr::get($getPlanDetails, 'charges.' . $planRequest[1] . '.charge', 0),
                'product_ids' => array_filter([$planId, $planPriceId]),
                'custom_fields' => [
                    'plan_title' => Arr::get($getPlanDetails, 'title'),
                    'plan_frequency' => $planRequest[1],
                    'vendor_uid' => $this->subscriber()?->_uid ?? null,
                ],
                'website_url' => config('app.url'),
            ], $this->affiliateTracker->captureContext($request));
        } catch (IncompletePayment $exception) {
            return redirect()->route(
                'cashier.payment',
                [$exception->payment->id, 'redirect' => route('subscription.read.show')]
            );
        } catch (\Exception $e) {
            setRedirectAlertMessage($e->getMessage(), 'error');
            return redirect()->route('subscription.read.show');
        }

        return redirect(route('subscription.read.show'));
    }
}
