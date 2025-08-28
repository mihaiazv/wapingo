@extends('layouts.app', ['title' => __tr('Stripe Payment Links Settings')])
@section('content')
    @include('users.partials.header', [
    'title' => __tr('Stripe Payment Links Settings'),
    'description' => '',
    'class' => 'col-lg-7'
    ])
    <div class="container-fluid">
        <div class="row">
            <div class="col-lg-12">
                <!-- card start -->
                <div class="card">
                    <!-- card body -->
                    <div class="card-body">
                        <!-- include related view -->
                        <div class="row">
                            <div class="col-md-8" x-cloak>
                            <!-- Page Heading -->
                            <h1>
                                <?= __tr('Stripe Payment Links for Contacts') ?>
                            </h1>
                            @if (isDemo())
                            <div class="alert alert-warning">
                                <strong>{{  __tr('Demo Alert:') }}</strong>
                                {{  __tr('Please note this is addon feature') }}
                            </div>
                            @endif
                            <form class="lw-ajax-form lw-form" method="post" action="<?= route('vendor.settings.write.update', ['pageType' => 'lw_addon_contact_stripe_payment_links']) ?>" >
                                <fieldset>
                                    <legend>{{  __tr('Basic Info') }}</legend>
                                    <x-lw.input-field type="text" id="lw_addon_cpl_stripe_currency_code" data-form-group-class="col-md-6 col-sm-12" value="{{ getVendorSettings('lw_addon_cpl_stripe_currency_code') }}" :label="__tr('Currency Code')" name="lw_addon_cpl_stripe_currency_code"/>
                                    <x-lw.input-field type="text" id="lw_addon_cpl_stripe_button_label" data-form-group-class="col-md-6 col-sm-12" value="{{ getVendorSettings('lw_addon_cpl_stripe_button_label') }}" :label="__tr('Button Label')" name="lw_addon_cpl_stripe_button_label"/>
                                </fieldset>
                            <fieldset>
                                <legend>{!! __tr('Stripe API Keys & Webhook Settings') !!}</legend>
                                <p>{{  __tr('You will be able to send Stripe payment links from Chat Box') }}</p>

                                    <div class="my-4" x-cloak x-data="{lwVendorEndpointShow:{{ getVendorSettings('lw_addon_cpl_stripe_enable') ? 1 : 0 }}}">
                                        <x-lw.checkbox @click="lwVendorEndpointShow = !lwVendorEndpointShow" id="lwAddonEnablePaymentLinks" name="lw_addon_cpl_stripe_enable" :checked="getVendorSettings('lw_addon_cpl_stripe_enable')" data-lw-plugin="lwSwitchery" :label="__tr('Enable Stripe Payment Links for Contacts')" />
                                        <div>
                                            <x-lw.input-field type="text" id="lw_addon_cpl_stripe_secret_key" data-form-group-class="" placeholder="{{ getVendorSettings('lw_addon_cpl_stripe_secret_key') ? __tr('Key exists add new to update') : __tr('Add your stripe secret key') }}" :label="__tr('Stripe Secret Key')" name="lw_addon_cpl_stripe_secret_key"/>
                                            <div class="my-3">
                                                {{  __tr('On the following url you can access the keys') }}
                                                <a target="_blank" href="https://dashboard.stripe.com/developers/">https://dashboard.stripe.com/developers/</a>
                                            </div>
                                            <fieldset>
                                                <legend>{{  __tr('Stripe Webhook') }}</legend>
                                                <div class="my-3">
                                                    {{  __tr('Go to following url and add webhook endpoint') }}
                                                    <a target="_blank" href="https://dashboard.stripe.com/webhooks/create">https://dashboard.stripe.com/webhooks/create</a>
                                                    <h4 class="mt-4">{{  __tr('Select following events whiles creating webhook') }}</h4>
                                                    <strong>checkout.session.completed</strong>
                                                </div>
                                                <div class="text-danger help-text mt-2 text-sm">{{  __tr('IMPORTANT: It is very important that you should add this Webhook to Stripe account, as all the payment information gets updated using this webhook.') }}</div>
                                                <div class="form-group">
                                                    <label for="lwStripeWebhookUrl">{{ __tr('Stripe Webhook Endpoint') }}</label>
                                                    <div class="input-group">
                                                        <input type="text" class="form-control" readonly id="lwStripeWebhookUrl" value="{{ getViaSharedUrl(route('addon.contact_payment_links_stripe.vendor.payment_webhook.write', [
                                                            'vendorUid' => getVendorUid()
                                                        ])) }}">
                                                        <div class="input-group-append">
                                                            <button class="btn btn-outline-light" type="button" onclick="lwCopyToClipboard('lwStripeWebhookUrl')">
                                                                <?= __tr('Copy') ?>
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </fieldset>
                                            <x-lw.input-field type="text" id="lw_addon_cpl_stripe_webhook_secret" data-form-group-class="" placeholder="{{ getVendorSettings('lw_addon_cpl_stripe_webhook_secret') ? __tr('Key exists add new to update') : __tr('Add your stripe secret key') }}" :label="__tr('Stripe Webhook Signing Secret')" name="lw_addon_cpl_stripe_webhook_secret"/>
                                        </div>
                                    </div>
                        </fieldset>

                        <fieldset>
                            <legend>{{  __tr('Template for Payment Completion') }}</legend>
                            <div class="alert alert-warning">{{  __tr('Please note you only need to select Text Based template with maximum of 4 variables, otherwise message will not be sent.') }}</div>
                            <div class="row">
                                <x-lw.input-field placeholder="{!! __tr('Select & Configure Template') !!}" type="selectize"
                                data-lw-plugin="lwSelectize" data-selected=" " type="select"
                                id="lwField_templateSelection" name="lw_addon_cpl_stripe_payment_comp_tml_uid" data-form-group-class="col-md-6 col-sm-12"
                                class="custom-select" data-selected="{{ getVendorSettings('lw_addon_cpl_stripe_payment_comp_tml_uid') }}" :label="__tr('Select Template')">
                                <x-slot name="selectOptions">
                                    <option value="">{{ __tr('Select & Configure Template') }}</option>
                                    @foreach ($whatsAppTemplates as $whatsAppTemplate)
                                    <option value="{{ $whatsAppTemplate->_uid }}">{{ $whatsAppTemplate->template_name }}
                                        ({{ $whatsAppTemplate->language }})</option>
                                    @endforeach
                                </x-slot>
                            </x-lw.input-field>
                            </div>
                            <div class="row">
                                <div class="alert">
                                    {{  __tr('Available variables as') }} @{{1}} => contact full name , @{{2}} => order id , @{{3}} => Amount, @{{4}} => Date
                                </div>
                            </div>
                            @if (getVendorSettings('lw_addon_cpl_stripe_payment_completion_tml'))
                            <div>
                                <a href="{{ route('addon.contact_payment_links_stripe.vendor.test_pc_template.write') }}" data-method="post" class="lw-btn btn btn-sm btn-dark lw-ajax-link-action">{{  __tr('Send Sample Test Template Message') }}</a>
                            </div>
                            @endif
                        </fieldset>
                                {{-- submit button --}}
                                <div class="mt-2">
                                    <button type="submit" href class="mt-2 btn btn-primary btn-user lw-btn-block-mobile">{{ __tr('Save') }}</button>
                                </div>
                            </form>
                            </div>
                        </div>
                        <!-- /include related view -->
                    </div>
                    <!-- /card body -->
                </div>
                <!-- card start -->
            </div>
        </div>
        </div>
@endsection()