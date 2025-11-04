<?php

namespace Addons\ContactStripePaymentLinks\Yantrana\Controllers;

use Stripe\StripeClient;
use App\Yantrana\Base\BaseRequestTwo;
use App\Yantrana\Base\BaseRequest;
use App\Yantrana\Components\BotReply\Controllers\BotReplyController;
use Illuminate\Support\Facades\Response;
use App\Yantrana\Base\AddonBaseController;
use App\Yantrana\Components\Contact\Repositories\ContactRepository;
use App\Yantrana\Components\WhatsAppService\Repositories\WhatsAppTemplateRepository;

class ContactStripePaymentLinksController extends AddonBaseController
{
    /**
     * Addon Namespace
     *
     * @var string
     */
    protected $addonNamespace = "ContactStripePaymentLinks";

    private $stripe;

    /**
     * Show Addon Settings Page
     *
     * @return view
     */
    public function showSettings()
    {
        validateVendorAccess('administrative');
        $whatsAppTemplateRepository = new WhatsAppTemplateRepository();
        $whatsAppApprovedTemplates = $whatsAppTemplateRepository->getApprovedTemplatesByNewest();
        return $this->addonView('settings', [
            'whatsAppTemplates' => $whatsAppApprovedTemplates
        ]);
    }
    
    private function generatePaymentLink(float $amount, string $contactIdOrUid, int $vendorId)
    {
        $orderId = uniqid('payment_');

        $this->stripe = new StripeClient(getVendorSettings('lw_addon_cpl_stripe_secret_key', null, null, $vendorId)); // folosește config

        // Creare Price
        $price = $this->stripe->prices->create([
            'unit_amount' => $amount * 100,
            'currency' => getVendorSettings('lw_addon_cpl_stripe_currency_code', null, null, $vendorId),
            'product_data' => [
                'name' => $orderId,
            ],
        ]);

        // Creare Payment Link
        $paymentLink = $this->stripe->paymentLinks->create([
            'line_items' => [[
                'price' => $price->id,
                'quantity' => 1,
            ]],
            'metadata' => [
                'order_id' => $orderId,
                'contact_uid' => $contactIdOrUid,
                'stripe_price_id' => $price->id,
            ],
        ]);

        return $paymentLink;
    }

    public function createPaymentLink(BaseRequestTwo $request)
    {
        $vendorId = getVendorId();
        $request->validate([
            'lw_send_payment_link_message' => 'string|min:1',
            'lw_send_payment_link_amount' => 'required|numeric|min:1',
            'bot_flow_uid' => 'string',
        ]);

        try {
            $paymentLink = $this->generatePaymentLink(
                $request->get('lw_send_payment_link_amount'),
                $request->get('bot_flow_uid'),
                $vendorId
            );

            $data = $request->all();
            $data['interactive_type'] = 'cta_url';
            $data['message_type'] = 'interactive';
            $data['header_type'] = '';
            $data['button_display_text'] = $request->get('button_display_text');
            $data['button_url'] = $paymentLink->url;
            $data['reply_text'] = $request->get('reply_text');

            $newRequest = new BaseRequest($data);

            app(BotReplyController::class)->processBotReplyCreate($newRequest);

            return $this->processResponse(1, [
                1 => __tr('Payment link'),
            ]);
        } catch (\Exception $e) {
            return $this->processResponse(2, [
                2 => $e->getMessage()
            ]);
        }
    }

    public function createAndSendPaymentLink(BaseRequestTwo $request)
    {
        validateVendorAccess('messaging');

        $vendorId = getVendorId();
        $request->validate([
            'lw_send_payment_link_message' => 'required|string|min:1',
            'lw_send_payment_link_amount' => 'required|numeric|min:1',
            'contactIdOrUid' => 'required|string',
        ]);

        try {
            $paymentLink = $this->generatePaymentLink(
                $request->get('lw_send_payment_link_amount'),
                $request->get('contactIdOrUid'),
                $vendorId
            );

            whatsAppServiceEngine()->processSendChatMessage([
                'messageBody' => $request->get('lw_send_payment_link_message'),
                'contactUid' => $request->get('contactIdOrUid')
            ], false, $vendorId, [
                'interaction_message_data' => [
                    'interactive_type' => 'cta_url',
                    'body_text' => $request->get('lw_send_payment_link_message'),
                    'cta_url' => [
                        'display_text' => getVendorSettings('lw_addon_cpl_stripe_button_label', null, null, $vendorId) ?: 'Pay',
                        'url' => $paymentLink->url,
                    ],
                ],
            ]);

            return $this->processResponse(1, [
                1 => __tr('Payment Link has been sent')
            ]);
        } catch (\Exception $e) {
            return $this->processResponse(2, [
                2 => $e->getMessage()
            ]);
        }
    }

    // Handle webhook for payment events
    public function handleStripeWebhook(BaseRequestTwo $request, $vendorUid)
    {
        
        file_put_contents(
    storage_path('logs/stripe_webhook.txt'),
    print_r($request->all(), true) . PHP_EOL . str_repeat('-', 50) . PHP_EOL,
    FILE_APPEND
);
        
        $vendorId = getPublicVendorId($vendorUid);
        if (! $vendorId) {
            return false;
        }
        $payload = $request->getContent();
        $signature = $request->header('Stripe-Signature');


        try {
            $event = \Stripe\Webhook::constructEvent(
                $payload,
                $signature,
                getVendorSettings('lw_addon_cpl_stripe_webhook_secret', null, null, $vendorId)
            );

            switch ($event->type) {
                case 'checkout.session.completed':
                    $session = $event->data->object;
                    // Extract metadata
                    $orderId = $session->metadata->order_id ?? null;
                    $contactUid = $session->metadata->contact_uid ?? null;
                    $amountTotal = $session->amount_total ?? null;
                    $currencyCode = $session->currency ?? null;
                    $sourceCurrencyAmount = $session->currency_conversion->amount_total ?? null;
                    $sourceCurrencyCode = $session->currency_conversion->source_currency ?? null;
                    $stripe = new \Stripe\StripeClient(getVendorSettings('lw_addon_cpl_stripe_secret_key', null, null, $vendorId));
                    try {
                        // disable the price
                        $stripe->prices->update($session->metadata->stripe_price_id, [
                            'active' => false,
                        ]);
                        // disable the payment link
                        $stripe->paymentLinks->update(
                            $session->payment_link, // Save this in your DB when creating the link
                            ['active' => false]
                        );
                    } catch (\Throwable $th) {
                        //throw $th;
                    }   
                    $whatsappPaymentCompletionTemplateUid = getVendorSettings('lw_addon_cpl_stripe_payment_comp_tml_uid', null, null, $vendorId);
                    $contactRepository = new ContactRepository();
                    $contact = $contactRepository->getVendorContact($contactUid, $vendorId);
                    if (__isEmpty($contact)) {
                        return false;
                    }
                    // format currency
                    $formattedAmount = $sourceCurrencyAmount ? (($amountTotal / 100) . ' ' . $currencyCode . ' (' . ($sourceCurrencyAmount / 100) . ' ' . $sourceCurrencyCode .') ') : ($amountTotal / 100) . ' ' . $currencyCode;
                    $contactRepository->updateIt($contact, [
                        '__data' => [
                            'contact_metadata' => [
                                'payments' => [
                                    $orderId => [
                                        'order_id' => $orderId,
                                        'formatted_amount' => $formattedAmount,
                                        'source_currency_amount' => ($sourceCurrencyAmount / 100),
                                        'source_currency_code' => $sourceCurrencyCode ?: 0,
                                        'amount' => ($amountTotal / 100),
                                        'currency_code' => $currencyCode,
                                        'paid_at' => $session->created,
                                        'formatted_paid_at' => formatDateTime($session->created, null, $vendorId),
                                        'payment_gateway' => 'stripe',
                                        'payment_intent' => $session->payment_intent,
                                    ]
                                ]
                            ]
                        ]
                    ]);
                    if ($whatsappPaymentCompletionTemplateUid) {
                        whatsAppServiceEngine()->sendTemplateMessageProcess(request()->create('/', 'null', [
                            'template_uid' => $whatsappPaymentCompletionTemplateUid,
                            'field_1' => $contact->full_name, // full name
                            'field_2' => $orderId, // txn or order id
                            'field_3' => $formattedAmount,
                            'field_4' => formatDateTime($session->created, null, $vendorId),
                        ]), $contactUid, false, null, $vendorId);
                    }
                    break;
                default:
            }
            return response()->json(['status' => 'success']);
        } catch (\UnexpectedValueException $e) {
            return response()->json(['error' => 'Invalid payload'], 400);
        } catch (\Stripe\Exception\SignatureVerificationException $e) {
            return response()->json(['error' => 'Invalid signature'], 400);
        }
    }

    /**
     * Test the payment complete template
     *
     * @return response
     */
    public function testPaymentCompleteTemplate()
    {
        validateVendorAccess('administrative');
        $vendorId = getVendorId();
        $whatsappPaymentCompletionTemplateUid = getVendorSettings('lw_addon_cpl_stripe_payment_comp_tml_uid', null, null, $vendorId);
        if ($whatsappPaymentCompletionTemplateUid) {
            $testContactUid = getVendorSettings('test_recipient_contact');
            $processResponse = whatsAppServiceEngine()->sendTemplateMessageProcess(request()->create('/', 'null', [
                'template_uid' => $whatsappPaymentCompletionTemplateUid,
                'field_1' => 'Sample Name', // txn or order id
                'field_2' => 'Sample Payment Id',
                'field_3' => '000 usd',
                'field_4' => formatDateTime(now(), null, $vendorId),
            ]), $testContactUid, false, null, $vendorId);
            return $this->processResponse($processResponse, [
                1 => __tr('Test message for payment complete template has been sent.')
            ]);
        }
        return $this->processResponse(2, [
            2 => __tr('Failed to send test message for payment complete template')
        ]);
    }
}
