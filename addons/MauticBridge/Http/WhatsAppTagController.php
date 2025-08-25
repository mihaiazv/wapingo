<?php
namespace Addons\MauticBridge\Http;

use Illuminate\Routing\Controller;
use Illuminate\Http\JsonResponse;

class WhatsAppTagController extends Controller
{
    /** POST /whapi/tag-wa  (numai auth) */
    public function __invoke(): JsonResponse
    {
        $user = auth()->user();
        if ($user) {
            \Addons\MauticBridge\MauticClient::tag($user, 'WhatsAppConnected');
            return response()->json(['ok' => true]);
        }
        return response()->json(['error' => 'unauthenticated'], 401);
    }
}