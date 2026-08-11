<?php

namespace App\Services\Admin;

use App\Models\Blacklist;
use App\Models\Order;
use App\Models\ReturnRequest;
use Illuminate\Support\Facades\Cache;

class FraudDetectionService
{
    /**
     * Evaluate risk for an order using live return queries and Redis blacklist Sets.
     */
    public function evaluateOrderRisk(Order $order): array
    {
        $reasons = [];

        // 1. Live real-time return requests check (bypasses stale batch statistics)
        if ($order->user_id) {
            $liveReturnsCount = ReturnRequest::where('user_id', $order->user_id)
                ->whereIn('status', ['approved', 'completed', 'refunded'])
                ->count();

            if ($liveReturnsCount >= 3) {
                $reasons[] = "High return history: Customer has {$liveReturnsCount} live return requests.";
            }
        }

        // 2. In-memory Redis cached Blacklist check
        $blacklists = Cache::remember('blacklists:all', 86400, function () {
            return Blacklist::all()->groupBy('type')->map(fn ($group) => $group->pluck('value')->toArray())->toArray();
        });

        $phones = $blacklists['phone'] ?? [];
        $ips = $blacklists['ip'] ?? [];
        $emails = $blacklists['email'] ?? [];

        if ($order->customer_phone && in_array($order->customer_phone, $phones)) {
            $reasons[] = "Blacklisted phone number: {$order->customer_phone}";
        }

        if ($order->customer_email && in_array(strtolower($order->customer_email), array_map('strtolower', $emails))) {
            $reasons[] = "Blacklisted email address: {$order->customer_email}";
        }

        $requestIp = request()?->ip();
        if ($requestIp && in_array($requestIp, $ips)) {
            $reasons[] = "Blacklisted IP address: {$requestIp}";
        }

        $isFlagged = count($reasons) > 0;

        if ($isFlagged) {
            $order->update([
                'is_flagged_fraud' => true,
                'fraud_reasons' => $reasons,
            ]);
        }

        return [
            'is_flagged' => $isFlagged,
            'reasons' => $reasons,
        ];
    }

    /**
     * Add an item to the blacklist and invalidate Redis cache.
     */
    public function addToBlacklist(string $type, string $value, ?string $reason = null, ?int $addedById = null): Blacklist
    {
        $blacklist = Blacklist::create([
            'type' => strtolower($type),
            'value' => trim($value),
            'reason' => $reason,
            'added_by_id' => $addedById,
        ]);

        Cache::forget('blacklists:all');

        return $blacklist;
    }

    /**
     * Remove an item from the blacklist and invalidate Redis cache.
     */
    public function removeFromBlacklist(int $id): void
    {
        $blacklist = Blacklist::findOrFail($id);
        $blacklist->delete();

        Cache::forget('blacklists:all');
    }
}
