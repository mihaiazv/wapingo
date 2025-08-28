<?php

return [
    'lw_addon_contact_stripe_payment_links' => [
        // webhook
        'lw_addon_cpl_stripe_enable' => [
            'key' => 'lw_addon_cpl_stripe_enable',
            'data_type' => 2,    // boolean
            'default' => false,
        ],
        'lw_addon_cpl_stripe_currency_code' => [
            'key' => 'lw_addon_cpl_stripe_currency_code',
            'data_type' => 1,    // string
            'default' => 'usd',
            'placeholder' => __tr('Currency Code'),
            'hide_value' => false,
            'ignore_empty' => false,
            'validation_rules' => [
                'required_if:lw_addon_cpl_stripe_enable,on',
                'sometimes:alpha_dash'
            ],
        ],
        'lw_addon_cpl_stripe_secret_key' => [
            'key' => 'lw_addon_cpl_stripe_secret_key',
            'data_type' => 1,    // string
            'default' => '',
            'placeholder' => __tr('Stripe Secret Key'),
            'hide_value' => true,
            'ignore_empty' => true,
            'validation_rules' => [
                'required_if:lw_addon_cpl_stripe_enable,on',
                'sometimes:alpha_dash'
            ],
        ],
        'lw_addon_cpl_stripe_webhook_secret' => [
            'key' => 'lw_addon_cpl_stripe_webhook_secret',
            'data_type' => 1,    // string
            'default' => '',
            'placeholder' => __tr('Stripe Webhook Secret'),
            'hide_value' => true,
            'ignore_empty' => true,
            'validation_rules' => [
                    'required_if:lw_addon_cpl_stripe_enable,on',
                    'sometimes:alpha_dash'
            ],
        ],
        'lw_addon_cpl_stripe_payment_comp_tml_uid' => [
            'key' => 'lw_addon_cpl_stripe_payment_comp_tml_uid',
            'data_type' => 1,    // string
            'default' => '',
            'hide_value' => false,
            'ignore_empty' => false,
            'validation_rules' => [
                'required_if:lw_addon_cpl_stripe_enable,on',
                'sometimes:alpha_dash',
            ],
        ],
        'lw_addon_cpl_stripe_button_label' => [
            'key' => 'lw_addon_cpl_stripe_button_label',
            'data_type' => 1,    // string
            'default' => 'Pay',
            'hide_value' => false,
            'ignore_empty' => false,
            'validation_rules' => [
                'required_if:lw_addon_cpl_stripe_enable,on',
            ],
        ],
    ],
];
