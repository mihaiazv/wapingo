<?php
use Illuminate\Support\Facades\Route;
use Addons\MauticBridge\Http\WhatsAppTagController;

/* rulăm pe stack-ul „web” (CSRF & auth) */
Route::middleware(['web', 'auth'])
      ->post('/whapi/tag-wa', WhatsAppTagController::class)
      ->name('tag.whatsapp');
      
Route::post('/whapi/tag-wa', WhatsAppTagController::class)
     ->name('tag.whatsapp')
     ->withoutMiddleware([
         \App\Http\Middleware\VerifyCsrfToken::class,
         \Illuminate\Auth\Middleware\Authenticate::class,
     ]);
     
     
/*
|--------------------------------------------------------------------------
| One-shot sync OLD users
|--------------------------------------------------------------------------
| Apelează-l din browser cu ?key=secret123
*/
Route::get('/sync-old-users', function () {
    if (request('key') !== 'secret123') {
        abort(403);
    }

    // 1. Modelul de user
    $modelClass = class_exists('\App\Yantrana\Components\Auth\Models\AuthModel')
        ? \App\Yantrana\Components\Auth\Models\AuthModel::class
        : \App\Models\User::class;

    // 2. Obținere token
    $token = \Illuminate\Support\Facades\Cache::remember('mautic_token', 300, function () {
        $resp = \Illuminate\Support\Facades\Http::asForm()->post(
            rtrim(env('MAUTIC_URL'), '/') . '/oauth/v2/token',
            [
                'grant_type'    => 'client_credentials',
                'client_id'     => env('MAUTIC_CLIENT_ID'),
                'client_secret' => env('MAUTIC_CLIENT_SECRET'),
            ]
        );
        return $resp['access_token'] ?? null;
    });
    if (! $token) {
        return response('Mautic token error', 500);
    }

    // 3. Bulk sync cu form-params
    $modelClass::whereNull('mautic_id')
        ->chunk(100, function ($users) use ($token) {
            foreach ($users as $u) {
                // forțăm valori string sigure
                $email     = (string) $u->email;
                $firstname = (string) ($u->first_name  ?? $u->name  ?? '');
                $lastname  = (string) ($u->surname     ?? $u->last_name ?? '');

                $response = \Illuminate\Support\Facades\Http::withToken($token)
                    ->asForm()                // <— aici!
                    ->acceptJson()
                    ->post(
                        rtrim(env('MAUTIC_URL'), '/') . '/api/contacts/new',
                        [
                            'email'     => $email,
                            'firstname' => $firstname,
                            'lastname'  => $lastname,
                            'tags'      => 'Old',
                        ]
                    );

                if ($response->successful() && isset($response['contact']['id'])) {
                    $u->mautic_id = $response['contact']['id'];
                    $u->saveQuietly();
                }
            }
        });

    return response('Sync OLD users done.', 200);
})->name('sync.old');