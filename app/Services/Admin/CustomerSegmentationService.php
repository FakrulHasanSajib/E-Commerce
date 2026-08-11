<?php

namespace App\Services\Admin;

use App\Models\CustomerSegment;
use App\Models\Order;
use App\Models\ReturnRequest;
use App\Models\User;

class CustomerSegmentationService
{
    /**
     * Recalculate segment classification for a given user.
     */
    public function recalculateForUser(User $user): CustomerSegment
    {
        $totalSpent = (float) Order::where('user_id', $user->id)->where('status', 'delivered')->sum('grand_total');
        $ordersCount = (int) Order::where('user_id', $user->id)->count();
        $returnsCount = (int) ReturnRequest::where('user_id', $user->id)->count();
        $lastOrder = Order::where('user_id', $user->id)->latest('created_at')->first();
        $lastOrderedAt = $lastOrder?->created_at;

        // Customer Segment Classification Boundary Rules
        if ($returnsCount >= 3) {
            $segment = 'high_risk';
        } elseif ($totalSpent >= 50000.00) {
            $segment = 'vip';
        } elseif ($ordersCount >= 5) {
            $segment = 'frequent_buyer';
        } elseif ($lastOrderedAt && $lastOrderedAt->isBefore(now()->subDays(90))) {
            $segment = 'dormant';
        } else {
            $segment = 'new';
        }

        return CustomerSegment::updateOrCreate(
            ['user_id' => $user->id],
            [
                'segment' => $segment,
                'total_spent' => $totalSpent,
                'orders_count' => $ordersCount,
                'returns_count' => $returnsCount,
                'last_ordered_at' => $lastOrderedAt,
            ]
        );
    }
}
