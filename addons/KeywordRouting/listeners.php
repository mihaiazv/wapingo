<?php
use Illuminate\Support\Facades\Event;
use App\Events\VendorChannelBroadcast;
use Addons\KeywordRouting\Listeners\KeywordRoutingListener;

// 1. Confirmăm că fișierul este inclus
//\Log::info('listeners.php loaded!');

// 2. Înregistrăm KeywordRoutingListener pentru eventul VendorChannelBroadcast
Event::listen(
    VendorChannelBroadcast::class,
    [KeywordRoutingListener::class, 'handle']
);