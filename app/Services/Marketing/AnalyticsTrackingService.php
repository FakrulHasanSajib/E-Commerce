<?php

namespace App\Services\Marketing;

use App\Models\User;

class AnalyticsTrackingService
{
    /**
     * Generate Meta Facebook Conversions API (CAPI) event payload.
     */
    public function generateFacebookCapiPayload(string $eventName, array $data, ?User $user = null): array
    {
        return [
            'data' => [
                [
                    'event_name' => $eventName,
                    'event_time' => time(),
                    'action_source' => 'website',
                    'user_data' => [
                        'em' => $user?->email ? hash('sha256', strtolower(trim($user->email))) : null,
                        'ph' => $user?->phone ? hash('sha256', preg_replace('/\D/', '', $user->phone)) : null,
                    ],
                    'custom_data' => [
                        'currency' => $data['currency'] ?? 'BDT',
                        'value' => (float) ($data['value'] ?? 0),
                        'content_ids' => $data['content_ids'] ?? [],
                        'content_type' => 'product',
                    ],
                ],
            ],
        ];
    }

    /**
     * Generate Google Tag Manager / GA4 DataLayer payload.
     */
    public function generateGtmDataLayer(string $eventName, array $data): array
    {
        return [
            'event' => $eventName,
            'ecommerce' => [
                'currency' => $data['currency'] ?? 'BDT',
                'value' => (float) ($data['value'] ?? 0),
                'items' => $data['items'] ?? [],
            ],
        ];
    }
}
