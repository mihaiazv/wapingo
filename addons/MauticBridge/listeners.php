<?php
use Illuminate\Support\Facades\Event;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Events\Verified;
use Addons\MauticBridge\MauticClient;

/* ───────── Creare cont ──────── */
Event::listen(Registered::class, fn($e) =>
    MauticClient::create($e->user)          // tag New
);

/* ───────── Prima / următoare logare ──────── */
Event::listen(Login::class, function ($e) {
    if ($e->user->last_login_at === null) {
        MauticClient::tag($e->user, 'FirstLogin');
    }
});

/* ───────── Logout ──────── */
Event::listen(Logout::class, fn($e) =>
    MauticClient::tag($e->user, 'LoggedOut')
);

/* ───────── Reset parolă ──────── */
Event::listen(PasswordReset::class, fn($e) =>
    MauticClient::tag($e->user, 'PasswordReset')
);

/* ───────── Verificare e-mail ──────── */
Event::listen(Verified::class, fn($e) =>
    MauticClient::tag($e->user, 'EmailVerified')
);

/* ───────── Billing (Cashier) ──────── */
$cashier = [
    Laravel\Cashier\Events\SubscriptionCreated::class   => 'Subscribed',
    Laravel\Cashier\Events\SubscriptionUpdated::class   => 'PlanUpgraded',
    Laravel\Cashier\Events\SubscriptionCancelled::class => 'SubscriptionCancelled',
    Laravel\Cashier\Events\PaymentFailed::class         => 'PaymentFailed',
];

foreach ($cashier as $evt => $tag) {
    if (class_exists($evt)) {
        Event::listen($evt, fn($e) => MauticClient::tag($e->billable, $tag));
    }
}

/* ─────────  Dacă mai adaugi acțiuni proprii  ────────
   Ex.: EmbeddedSignIn, ContactsImported … creezi eveniment
   în app/Events și îl pui aici:
   Event::listen(App\Events\EmbeddedSignIn::class,
       fn($e) => MauticClient::tag($e->user, 'EmbeddedSignIn'));
*/