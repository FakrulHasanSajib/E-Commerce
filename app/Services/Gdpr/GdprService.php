<?php

namespace App\Services\Gdpr;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class GdprService
{
    /**
     * Export all customer PII data as JSON structure.
     */
    public function exportUserData(User $user): array
    {
        return [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'wallet_balance' => $user->wallet_balance,
                'loyalty_points' => $user->loyalty_points,
                'created_at' => $user->created_at->toIso8601String(),
            ],
            'addresses' => $user->addresses()->get()->toArray(),
            'orders' => $user->orders()->with('items')->get()->toArray(),
            'reviews' => $user->reviews()->get()->toArray(),
            'wallet_transactions' => $user->walletTransactions()->get()->toArray(),
            'loyalty_transactions' => $user->loyaltyTransactions()->get()->toArray(),
        ];
    }

    /**
     * Anonymize user account PII and revoke auth tokens.
     */
    public function anonymizeAccount(User $user, string $password): void
    {
        if (! Hash::check($password, $user->password)) {
            throw ValidationException::withMessages([
                'password' => 'The provided password does not match our records.',
            ]);
        }

        // 1. Delete user addresses
        $user->addresses()->delete();

        // 2. Revoke Sanctum tokens
        $user->tokens()->delete();

        // 3. Anonymize user record (Note: Order snapshot PII is preserved for tax/financial audit compliance)
        $user->update([
            'name' => 'Anonymized Customer',
            'email' => "anonymized_{$user->id}_".Str::random(6).'@anonymized.local',
            'phone' => null,
            'password' => Hash::make(Str::random(32)),
            'wallet_balance' => 0.00,
            'loyalty_points' => 0,
        ]);
    }
}
