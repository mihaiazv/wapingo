<?php
// addons/MauticBridge/SyncOldUsers.php

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

// 1. Modelul corect
$model = class_exists('\App\Yantrana\Components\Auth\Models\AuthModel')
       ? \App\Yantrana\Components\Auth\Models\AuthModel::class
       : \App\Models\User::class;

// 2. Obținem tokenul Mautic
$token = Cache::remember('mautic_token', 300, function () {
    $resp = Http::asForm()->post(
        rtrim(env('MAUTIC_URL'), '/') . '/oauth/v2/token',
        [
            'grant_type'    => 'client_credentials',
            'client_id'     => env('MAUTIC_CLIENT_ID'),
            'client_secret' => env('MAUTIC_CLIENT_SECRET'),
        ]
    );
    return $resp['access_token'] ?? null;
});

// 3. Helper pentru API
function mapi(string $uri, array $data) use ($token) {
    return Http::withToken($token)
               ->acceptJson()
               ->post(rtrim(env('MAUTIC_URL'), '/') . '/api/' . ltrim($uri, '/'), $data);
}

// 4. Parcurgem userii
$model::whereNull('mautic_id')->chunk(100, function ($users) {
    foreach ($users as $u) {
        $resp = mapi('contacts/new', [
            'email'     => $u->email,
            'firstname' =>  $u->first_name ?? '',
            'lastname'  =>  $u->last_name ?? '',
            'tags'      => 'Old',
        ]);
        if ($resp->successful() && ($id = $resp['contact']['id'] ?? null)) {
            $u->mautic_id = $id;
            $u->saveQuietly();
        }
    }
});

echo "DONE\n";