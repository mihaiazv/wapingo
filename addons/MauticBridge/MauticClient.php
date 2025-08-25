<?php
namespace Addons\MauticBridge;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Database\Eloquent\Model;

class MauticClient
{
    /* ───── suprascriem doar helper-ele de autentificare ───── */

    private static function http()
    {
        return Http::withToken(self::token())  // Bearer <access_token>
                   ->acceptJson()->timeout(10);
    }

    private static function token(): string
    {
        // păstrăm tokenul ~5 min în cache (Laravel file cache funcționează pe cPanel)
        return Cache::remember('mautic_token', 300, function () {
            $resp = Http::asForm()->post(
                rtrim(env('MAUTIC_URL'), '/') . '/oauth/v2/token',
                [
                    'grant_type'    => 'client_credentials',
                    'client_id'     => env('MAUTIC_CLIENT_ID'),
                    'client_secret' => env('MAUTIC_CLIENT_SECRET'),
                ]
            );

            if (!$resp->successful()) {
                \Log::error('Mautic token error', [$resp->status(), $resp->body()]);
                throw new \RuntimeException('Mautic auth failed');
            }

            return $resp['access_token'];
        });
    }

    private static function u(string $uri): string
    {
        return rtrim(env('MAUTIC_URL'), '/') . '/api/' . ltrim($uri, '/');
    }

    /* ───────── restul metodelor rămân IDENTICE ───────── */

    public static function create(Model $u): void
    {
        $r = self::http()->post(self::u('contacts/new'), [
            'email'     => $u->email,
           // 'firstname' => $u->name,
            'firstname' => $u->first_name ?? $u->name ?? '',   // ← nou
            'lastname'  => $u->last_name  ?? '',               // ← opțional
            'tags'      => 'New',
        ]);

        if ($r->successful() && ($id = $r['contact']['id'] ?? null)) {
            $u->mautic_id = $id;
            $u->saveQuietly();
        }
    }

    public static function tag(Model $u, array|string $tags): void
    {
        if (!$u->mautic_id) { self::create($u); }

        $tags = is_array($tags) ? implode(',', $tags) : $tags;
        self::http()->patch(
            self::u("contacts/{$u->mautic_id}/edit"),
            ['tags' => '+' . $tags]
        );
    }
}