<?php

namespace Addons\WhapiAffiliate\Decorators;

use Addons\WhapiAffiliate\Services\AffiliateTracker;
use App\Yantrana\Base\BaseMailer;
use App\Yantrana\Components\Auth\Repositories\AuthRepository;
use App\Yantrana\Components\Dashboard\DashboardEngine;
use App\Yantrana\Components\Subscription\ManualSubscriptionEngine as BaseManualSubscriptionEngine;
use App\Yantrana\Components\Subscription\PaymentEngines\PaystackEngine;
use App\Yantrana\Components\Subscription\PaymentEngines\PaypalEngine;
use App\Yantrana\Components\Subscription\PaymentEngines\PhonePeEngine;
use App\Yantrana\Components\Subscription\PaymentEngines\RazorpayEngine;
use App\Yantrana\Components\Subscription\PaymentEngines\YoomoneyEngine;
use App\Yantrana\Components\Subscription\Repositories\ManualSubscriptionRepository;
use App\Yantrana\Components\Subscription\SubscriptionEngine as BaseSubscriptionEngine;
use App\Yantrana\Components\Vendor\Repositories\VendorRepository;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;

class ManualSubscriptionEngineDecorator extends BaseManualSubscriptionEngine
{
    protected AffiliateTracker $affiliateTracker;

    public function __construct(
        ManualSubscriptionRepository $manualSubscriptionRepository,
        VendorRepository $vendorRepository,
        PaypalEngine $paypalEngine,
        AuthRepository $authRepository,
        BaseMailer $baseMailer,
        DashboardEngine $dashboardEngine,
        RazorpayEngine $razorpayEngine,
        PaystackEngine $paystackEngine,
        YoomoneyEngine $yoomoneyEngine,
        BaseSubscriptionEngine $subscriptionEngine,
        PhonePeEngine $phonePeEngine,
        AffiliateTracker $affiliateTracker
    ) {
        parent::__construct(
            $manualSubscriptionRepository,
            $vendorRepository,
            $paypalEngine,
            $authRepository,
            $baseMailer,
            $dashboardEngine,
            $razorpayEngine,
            $paystackEngine,
            $yoomoneyEngine,
            $subscriptionEngine,
            $phonePeEngine
        );

        $this->affiliateTracker = $affiliateTracker;
    }

    public function processManualPayPreparation($request)
    {
        $planRequest = explode('___', $request->selected_plan);
        abortIf(!isset($planRequest[0]) or !isset($planRequest[1]), null, __tr('Invalid Plan or Frequency'));
        $planFrequencyKey = $planRequest[1];
        $planDetails = getPaidPlans($planRequest[0]);
        abortIf(!$planDetails, null, __tr('Invalid Plan or Frequency'));
        $planCharges = $planDetails['charges'][$planFrequencyKey]['charge'];
        $planChargesFormatted = formatAmount($planCharges, true, true);
        $planFrequencyTitle = $planDetails['charges'][$planFrequencyKey]['title'];
        $endsAt = now();
        $daysForCalculation = 0;
        switch ($planFrequencyKey) {
            case 'monthly':
                $endsAt = now()->addMonth();
                $daysForCalculation = now()->daysInMonth;
                break;
            case 'yearly':
                $endsAt = now()->addYear();
                $daysForCalculation = now()->daysInYear;
                break;
        }
        $vendorId = getVendorId();
        $existingRequestExist = false;
        $preparePlanDetails = [
            'plan_id' => $planDetails['id'],
            'plan_features' => $planDetails['features'],
            'plan_charges' => $planCharges,
            'plan_frequency' => $planFrequencyKey,
            // may prorated based on current plan etc
            'prorated_remaining_balance_days' => 0,
            'prorated_remaining_balance_amount' => 0,
            'existing_plan_days_adjusted' => 0
        ];
        // get the current subscription
        $currentActiveSubscription = $this->manualSubscriptionRepository->getCurrentActiveSubscription($vendorId);
        $existingPlanDaysAdjustments = false;
        $checkPlanUsages = $this->dashboardEngine->checkPlanUsages($planDetails, $vendorId);
        if ($checkPlanUsages) {
            return $this->engineFailedResponse(
                [
                'show_message' => true,
                'planDetails' => $planDetails,
                'existingRequestExist' => $existingRequestExist,
                'checkPlanUsages' => $checkPlanUsages,
            ],
                __tr('Overused features __overUsedFeatures__', [
                    '__overUsedFeatures__' => $checkPlanUsages
                ])
            );
        }
        // prorated adjustments
        if (!__isEmpty($currentActiveSubscription) and $planCharges and $currentActiveSubscription->charges and $currentActiveSubscription->ends_at) {
            $existingCreatedAt = Carbon::parse($currentActiveSubscription->created_at);
            $existingEndsAt = Carbon::parse($currentActiveSubscription->ends_at);
            $existingPlanCharges = $currentActiveSubscription->charges;
            // Calculate the total number of days in the billing period (from created_at to ends_at)
            $existingTotalDays = $existingCreatedAt->diffInDays($existingEndsAt);
            // Calculate the remaining days from today until ends_at
            $remainingDays = Carbon::now()->diffInDays($existingEndsAt, false);
            // Calculate daily charge
            $dailyCharge = 0;
            $proratedBalance = 0;
            if ($existingTotalDays) {
                $dailyCharge = $existingPlanCharges / ($currentActiveSubscription->charges_frequency == 'monthly' ? now()->daysInMonth : now()->daysInYear);
                // $dailyCharge = $existingPlanCharges / $existingTotalDays;
                // Calculate prorated balance
                $proratedBalance = round($dailyCharge * $remainingDays, 2);
            }
            if ($proratedBalance > 0) {
                $perDaysValueForNewPlan = $planCharges / $daysForCalculation;
                $daysForRemainingAmount = floor($proratedBalance / $perDaysValueForNewPlan);
                $endsAt = $endsAt->addDays($daysForRemainingAmount);
                // if there are lots of days added then we need to restrict it max possible year
                if ($endsAt->year > 9999) {
                    // max year
                    $endsAt = Carbon::create(9999, 12, 31, 23, 59, 59);
                }
                $preparePlanDetails = array_merge($preparePlanDetails, [
                    // may prorated charges based on current plan etc
                    'prorated_remaining_balance_days' => $remainingDays,
                    'prorated_remaining_balance_amount' => $proratedBalance,
                    'existing_plan_days_adjusted' => 1,
                ]);
                $existingPlanDaysAdjustments = true;
            }
        }

        // existing pending request
        $subscriptionRequestRecord = $this->manualSubscriptionRepository->fetchIt([
            'vendors__id' => $vendorId,
            'status' => 'initiated',
        ]);

        if (!__isEmpty($subscriptionRequestRecord)) {
            $this->manualSubscriptionRepository->deleteIt([
                'vendors__id' => $vendorId,
                'status' => 'initiated',
            ]);
            $subscriptionRequestRecord = null;
        }

        if (__isEmpty($subscriptionRequestRecord)) {
            $subscriptionRequestRecord = $this->manualSubscriptionRepository->fetchIt([
                'vendors__id' => $vendorId,
                'status' => 'pending',
            ]);
        }

        $affiliateContext = $this->affiliateTracker->captureContext($request);

        if (__isEmpty($subscriptionRequestRecord)) {
            $additionalMeta = [
                'prepared_plan_details' => $preparePlanDetails,
                'manual_txn_details' => [
                    'selected_payment_method' => $request->payment_method
                ],
            ];

            if (!__isEmpty($affiliateContext)) {
                $additionalMeta['affiliate_tracking'] = $affiliateContext;
            }

            $subscriptionRequestRecord = $this->manualSubscriptionRepository->storeIt([
                'plan_id' => $planDetails['id'],
                'charges_frequency' => $planFrequencyKey,
                'charges' => $planCharges,
                'remarks' => '',
                'ends_at' => $endsAt,
                'status' => 'initiated',
                'vendors__id' => $vendorId,
                '__data' => $additionalMeta,
            ]);
            abortIf(!$subscriptionRequestRecord, null, __tr('Failed to create subscription'));
        } else {
            $existingRequestExist = true;
            $planCharges = $subscriptionRequestRecord->charges;
            $planDetails['id'] = $subscriptionRequestRecord->plan_id;
            $planDetails['charges'][$planFrequencyKey]['charge'] = $planCharges;
            $planChargesFormatted = formatAmount($subscriptionRequestRecord->charges, true, true);

            if (!__isEmpty($affiliateContext) && __isEmpty(Arr::get($subscriptionRequestRecord->__data, 'affiliate_tracking'))) {
                $updatedAdditionalData = $subscriptionRequestRecord->__data ?? [];
                $updatedAdditionalData['affiliate_tracking'] = $affiliateContext;
                $this->manualSubscriptionRepository->updateIt($subscriptionRequestRecord, [
                    '__data' => $updatedAdditionalData,
                ]);
                $subscriptionRequestRecord->__data = $updatedAdditionalData;
            }
        }
        $upiId = getAppSettings('payment_upi_address');
        $payeeName = getAppSettings('name');
        $transactionRef = 'txn_ref_' . $subscriptionRequestRecord->_id;
        $transactionNote = "$payeeName-{$planDetails['id']}-$planFrequencyTitle-Subscription-" . $subscriptionRequestRecord->_id;
        $upiPaymentLink = createUpiLink($upiId, $payeeName, $planCharges, $transactionRef, $transactionNote);
        $paypalResponse = '';
        // check payment method is paypal
        if ($request->payment_method == 'paypal') {
            //paypal create order response
            $paypalResponse = $this->paypalEngine->paypalOrderCreate($planCharges, $subscriptionRequestRecord->_uid);
            if ($paypalResponse->failed()) {
                return $this->engineFailedResponse(
                    [
                        'show_message' => true
                    ],
                    $paypalResponse->message()
                );
            }
        }

        $phonePeInitiatePaymentData = null;
        // Check payment method is phon-pe
        if ($request->payment_method == 'phonepe') {
            $phonePeInitiatePaymentData = $this->phonePeEngine->initiatePayment($subscriptionRequestRecord->_uid, $planCharges);

            if ($phonePeInitiatePaymentData->failed()) {
                return $this->engineFailedResponse(
                    [
                        'show_message' => true
                    ],
                    $phonePeInitiatePaymentData->message()
                );
            }
        }

        return $this->engineSuccessResponse([
            'subscriptionRequestRecord' => $subscriptionRequestRecord,
            'existingRequestExist' => $existingRequestExist,
            'expiryDate' => $endsAt->format('Y-m-d'),
            'expiryDateFormatted' => formatDate($endsAt),
            'planChargesFormatted' => $planChargesFormatted,
            'existingPlanDaysAdjustments' => $existingPlanDaysAdjustments,
            'planDetails' => $planDetails,
            'planFrequencyTitle' => $planFrequencyTitle,
            'planCharges' => $planCharges,
            'paypalOrderId' => $paypalResponse['data']['createPaypalOrder']['id'] ?? null,
            'upiPaymentQRImageUrl' => route('vendor.generate.upi_payment_request', [
                'url' => base64_encode($upiPaymentLink)
            ]),
            'checkPlanUsages' => null,
            'phonePeInitiatePaymentData' => data_get($phonePeInitiatePaymentData, 'data.phonePeInitiateData')
        ]);
    }

    public function recordSentPaymentDetails($request)
    {
        $vendorId = getVendorId();
        $subscriptionRequestRecord = $this->manualSubscriptionRepository->fetchIt([
            '_uid' => $request['manual_subscription_uid'],
        ]);
        $vendorId = $subscriptionRequestRecord['vendors__id'];
        //get vendor details
        $vendorData = $this->vendorRepository->fetchIt($vendorId);
        $vendorUserData = $this->authRepository->fetchIt([
            'vendors__id' =>  $vendorId
        ]);
        if($subscriptionRequestRecord['status']== "active"){
            return $this->engineSuccessResponse([
                'txn_reference' => $request['txn_reference'],
                'redirectRoute' => route('payment.success.page', ['txnId' => $request['txn_reference']]),
            ], __tr('Transaction already Active'));
        };
        //current time
        $now = formatDate(Carbon::now());
        $subscriptionRequestRecord = $this->manualSubscriptionRepository->fetchIt([
            'vendors__id' => $vendorId,
            'status' => 'initiated',
            '_uid' => $request['manual_subscription_uid'],
        ]);

        if (__isEmpty($subscriptionRequestRecord)) {
            return $this->engineFailedResponse([], __tr('Invalid Subscription Request'));
        }

        $isTxnReferenceExists = $this->manualSubscriptionRepository->countIt([
            'vendors__id' => $vendorId,
            '__data->manual_txn_details->txn_reference' => $request['txn_reference'],
        ]);
        if ($isTxnReferenceExists) {
            return $this->engineSuccessResponse([], __tr('Transaction already been processed'));
        }
        // check payment method is paypal
        if ($subscriptionRequestRecord->__data['manual_txn_details']['selected_payment_method'] == 'paypal' || $subscriptionRequestRecord->__data['manual_txn_details']['selected_payment_method'] == 'razorpay' || $subscriptionRequestRecord->__data['manual_txn_details']['selected_payment_method'] == 'paystack' || $subscriptionRequestRecord->__data['manual_txn_details']['selected_payment_method'] == 'yoomoney' || $subscriptionRequestRecord->__data['manual_txn_details']['selected_payment_method'] == 'phonepe') {
            // deactivate existing active plans

            $this->manualSubscriptionRepository->updateItAll([
                'status' => 'active',
                'vendors__id' => $vendorId,
            ], [
                'status' => 'cancelled',
            ]);
            $planStructure = getPaidPlans($subscriptionRequestRecord['plan_id']);
            $affiliateContext = Arr::get($subscriptionRequestRecord['__data'], 'affiliate_tracking', []);

            $updatedAdditionalData = $subscriptionRequestRecord['__data'] ?? [];
            $updatedAdditionalData['manual_txn_details'] = array_merge(
                Arr::get($updatedAdditionalData, 'manual_txn_details', []),
                [
                    'txn_reference' => $request['txn_reference'],
                    'txn_date' => now(),
                ]
            );
            if (!__isEmpty($affiliateContext)) {
                $updatedAdditionalData['affiliate_tracking'] = $affiliateContext;
            }

            //update subscription request record
            if ($this->manualSubscriptionRepository->updateIt($subscriptionRequestRecord, [
                'status' => 'active',
                '__data' => $updatedAdditionalData,
            ])) {
                $this->affiliateTracker->trackSubscription([
                    'order_id' => $subscriptionRequestRecord['_uid'],
                    'order_currency' => config('cashier.currency', 'USD'),
                    'order_total' => $subscriptionRequestRecord['charges'],
                    'product_ids' => array_filter([$subscriptionRequestRecord['plan_id']]),
                    'custom_fields' => [
                        'plan_title' => Arr::get($planStructure, 'title'),
                        'plan_frequency' => $subscriptionRequestRecord['charges_frequency'],
                        'vendor_uid' => $vendorData['_uid'] ?? null,
                    ],
                    'website_url' => config('app.url'),
                ], $affiliateContext);
                return $this->engineSuccessResponse([
                    'txn_reference' => $request['txn_reference'],
                    'redirectRoute' => route('payment.success.page', ['txnId' => $request['txn_reference']]),
                ]);
            }
        }
        // if manual subscription request
        else {
            //fetch plan details
            $planStructure = getPaidPlans($subscriptionRequestRecord['plan_id']);
            //subscription mail data
            $emailData = [
               'adminName' => $vendorUserData['first_name'].' '.$vendorUserData['last_name'],
               'userName' => $vendorData['title'],
               'senderEmail' => $vendorUserData['email'],
               'toEmail' => getAppSettings('contact_email'),
               'subject' =>__tr("Manual subscription request mail"),
               'requested_at' => $now,
               'planTitle' => $planStructure['title'],
               'planCharges' => $subscriptionRequestRecord['charges'],
               'planFrequency' => $subscriptionRequestRecord['charges_frequency'],
               'txnReference' => $request->txn_reference,
               'txnDate' => formatDate($request->txn_date),
               'subscriptionPageUrl' => URL::route('central.vendor.details', ['vendorIdOrUid' => $vendorData['_uid']]),
         ];
            if ($this->manualSubscriptionRepository->updateIt($subscriptionRequestRecord, [
                'status' => 'pending',
                '__data' => [
                    'manual_txn_details' => [
                        'txn_reference' => $request->txn_reference,
                        'txn_date' => $request->txn_date,
                    ]
                ]
            ])) {
                //send mail to admin of manual subscription request.
                $this->baseMailer->notifyAdmin($emailData['subject'], 'manual-subscription-request', $emailData, 2);
                return $this->engineSuccessResponse();
            }
        }
        return $this->engineFailedResponse([], __tr('Failed to record your payment details'));
    }
}
