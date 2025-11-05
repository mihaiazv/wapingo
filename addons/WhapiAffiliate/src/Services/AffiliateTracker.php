<?php

namespace Addons\WhapiAffiliate\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AffiliateTracker
{
    /**
     * Affiliate tracking endpoint.
     */
    protected string $endpoint = 'https://parteneri.whapi.ro/integration/addOrder';

    /**
     * Capture affiliate related context from the current request.
     */
    public function captureContext(?Request $request = null): array
    {
        $request = $request ?? (app()->bound('request') ? app('request') : null);

        if (! $request instanceof Request) {
            return [];
        }

        $affiliateId = $request->query('af_id', $request->cookie('af_id', ''));
        $referer = $request->headers->get('referer', '');
        $userAgent = (string) $request->userAgent();

        $ip = $this->resolveClientIp($request);

        return array_filter([
            'affiliate_id' => $affiliateId,
            'ip' => $ip,
            'user_agent' => $userAgent,
            'referer' => $referer,
        ], static fn ($value) => $value !== null && $value !== '');
    }

    /**
     * Send subscription data to the affiliate tracking platform.
     */
    public function trackSubscription(array $details, array $context = []): void
    {
        $orderId = Arr::get($details, 'order_id');

        if (! $orderId) {
            return;
        }

        $orderTotal = Arr::get($details, 'order_total', 0);
        if (is_numeric($orderTotal)) {
            $orderTotal = number_format((float) $orderTotal, 2, '.', '');
        } else {
            $orderTotal = (string) $orderTotal;
        }

        $orderCurrency = strtoupper((string) Arr::get(
            $details,
            'order_currency',
            config('cashier.currency', 'USD')
        ));

        $productIds = array_filter(array_map('strval', Arr::get($details, 'product_ids', [])));
        $websiteUrl = (string) Arr::get($details, 'website_url', config('app.url'));

        $customFields = Arr::get($details, 'custom_fields', []);
        $customFieldPayload = [];

        foreach ($customFields as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            $customFieldPayload[] = [
                'key' => (string) $key,
                'value' => is_scalar($value) ? (string) $value : json_encode($value),
            ];
        }

        $context = array_merge($this->captureContext(), $context);
        $context = array_filter($context, static fn ($value) => $value !== null && $value !== '');

        $affiliateData = [
            'order_id' => $orderId,
            'order_currency' => $orderCurrency,
            'order_total' => $orderTotal,
            'product_ids' => implode(',', $productIds),
            'af_id' => Arr::get($context, 'affiliate_id', ''),
            'ip' => Arr::get($context, 'ip', ''),
            'base_url' => base64_encode($websiteUrl),
            'customFields' => json_encode($customFieldPayload),
            'script_name' => 'laravel_app',
        ];

        $userAgent = Arr::get($context, 'user_agent', '');
        $referer = Arr::get($context, 'referer', $websiteUrl);

        try {
            $response = Http::timeout(5)
                ->withHeaders(array_filter([
                    'User-Agent' => $userAgent,
                    'Referer' => $referer,
                ]))
                ->get($this->endpoint, $affiliateData);

            if ($response->failed()) {
                Log::warning('Affiliate tracking request failed.', [
                    'endpoint' => $this->endpoint,
                    'order_id' => $orderId,
                    'status' => $response->status(),
                ]);
            }
        } catch (\Throwable $exception) {
            Log::warning('Affiliate tracking request threw an exception.', [
                'endpoint' => $this->endpoint,
                'order_id' => $orderId,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * Resolve the most accurate client IP possible.
     */
    protected function resolveClientIp(Request $request): string
    {
        $candidates = [
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR',
        ];

        $serverData = [];

        if (method_exists($request, 'server')) {
            $rawServer = $request->server();

            if (is_array($rawServer)) {
                $serverData = $rawServer;
            } elseif (is_object($rawServer) && method_exists($rawServer, 'all')) {
                $serverData = $rawServer->all();
            }
        }

        if (empty($serverData) && property_exists($request, 'server') && is_object($request->server)) {
            if (method_exists($request->server, 'all')) {
                $serverData = $request->server->all();
            }
        }

        if (! empty($_SERVER)) {
            $serverData = array_merge($serverData, $_SERVER);
        }

        $normalizedServer = [];

        foreach ($serverData as $key => $value) {
            $normalizedServer[strtoupper($key)] = $value;
        }

        foreach ($candidates as $header) {
            $value = $normalizedServer[$header] ?? null;

            if ($value) {
                $firstIp = trim(explode(',', $value)[0]);

                if ($firstIp !== '') {
                    return $firstIp;
                }
            }
        }

        return (string) $request->ip();
    }
}
