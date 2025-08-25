<?php
namespace Addons\MauticBridge;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Yantrana\Components\Configuration\Models\ConfigurationModel;


class MauticBridgeServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        /* 1.  listener-ele standard (New, FirstLogin, etc.) */
        require_once __DIR__.'/listeners.php';
        
        
          //Route din Service Provider
        $this->loadRoutesFrom(__DIR__.'/routes.php');

        /* 2.  adăugăm coloana mautic_id dacă lipseşte */
        if (!\Schema::hasColumn('users', 'mautic_id')) {
            \Schema::table('users', function ($t) {
                $t->unsignedBigInteger('mautic_id')->nullable()->after('remember_token');
            });
        }

        /* 3.  contact nou pentru AuthModel::created */
        if (class_exists('\App\Yantrana\Components\Auth\Models\AuthModel')) {
            \App\Yantrana\Components\Auth\Models\AuthModel::created(function ($u) {
                if (!$u->mautic_id) {
                    \Addons\MauticBridge\MauticClient::create($u);   // tag New
                }
            });
        }

        /* ─── WhatsAppConnected ───────────────────────────────────── */
        ConfigurationModel::saved(function ($setting) {

            if ($setting->name !== 'whatsapp_business_account_id') {
                return;                     // nu e setarea care ne interesează
            }
            if (empty($setting->value)) {
                return;                     // încă nu e populată
            }

            // utilizatorul curent = vendor owner care a făcut setup-ul
            $user = auth()->user();         // merge pentru Console/Vendor
            if (!$user) {
                Log::warning('WhatsAppConnected: user null');
                return;
            }

            \Addons\MauticBridge\MauticClient::tag($user, 'WhatsAppConnected');
            Log::info('WhatsAppConnected pus pe '.$user->email);
        });
    
        
        
        
    }
}