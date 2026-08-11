<template>
  <div class="max-w-7xl mx-auto px-4 py-8">
    <div class="grid grid-cols-1 lg:grid-cols-4 gap-8">
      <!-- Account Sidebar Navigation -->
      <aside class="lg:col-span-1">
        <div class="bg-white rounded-2xl border border-slate-100 shadow-xs overflow-hidden">
          <!-- User Avatar Header -->
          <div class="bg-gradient-to-r from-indigo-900 to-slate-900 p-6 text-white space-y-2">
            <div class="w-12 h-12 rounded-2xl bg-indigo-500/30 border border-indigo-400/40 flex items-center justify-center text-xl font-black">
              {{ authStore.user?.name?.charAt(0)?.toUpperCase() || 'U' }}
            </div>
            <div class="font-bold text-sm">{{ authStore.user?.name }}</div>
            <div class="text-indigo-200 text-xs">{{ authStore.user?.email }}</div>
          </div>

          <!-- Wallet & Loyalty Quick Stats -->
          <div class="grid grid-cols-2 border-b border-slate-100">
            <div class="p-3 text-center border-r border-slate-100">
              <div class="text-xs text-slate-400">Wallet</div>
              <div class="font-black text-indigo-600 text-sm">৳{{ authStore.user?.wallet_balance || '0' }}</div>
            </div>
            <div class="p-3 text-center">
              <div class="text-xs text-slate-400">Points</div>
              <div class="font-black text-amber-600 text-sm">{{ authStore.user?.loyalty_points || '0' }}</div>
            </div>
          </div>

          <!-- Nav Links -->
          <nav class="p-3 space-y-0.5 text-xs">
            <NuxtLink v-for="link in navLinks" :key="link.to" :to="link.to"
              class="flex items-center gap-3 px-3 py-2.5 rounded-xl font-medium transition-all text-slate-600 hover:text-indigo-600 hover:bg-indigo-50"
              active-class="bg-indigo-50 text-indigo-700 font-bold"
              exact-active-class="bg-indigo-50 text-indigo-700 font-bold"
            >
              <span>{{ link.icon }}</span>
              <span>{{ link.label }}</span>
            </NuxtLink>

            <button
              @click="authStore.logout()"
              class="w-full flex items-center gap-3 px-3 py-2.5 rounded-xl font-medium text-rose-500 hover:bg-rose-50 transition-all mt-2 border-t border-slate-100 pt-4"
            >
              <span>🚪</span>
              <span>Sign Out</span>
            </button>
          </nav>
        </div>
      </aside>

      <!-- Main Content Area -->
      <main class="lg:col-span-3">
        <slot />
      </main>
    </div>
  </div>
</template>

<script setup lang="ts">
import { useAuthStore } from '~/stores/auth';

const authStore = useAuthStore();

const navLinks = [
  { to: '/account', icon: '👤', label: 'Profile & Security' },
  { to: '/account/orders', icon: '📦', label: 'My Orders' },
  { to: '/account/wishlist', icon: '💝', label: 'Wishlist' },
  { to: '/account/addresses', icon: '📍', label: 'Saved Addresses' },
  { to: '/account/wallet', icon: '💳', label: 'Wallet Balance' },
  { to: '/account/loyalty', icon: '⭐', label: 'Loyalty Points' },
  { to: '/account/returns', icon: '↩️', label: 'Return Requests' },
  { to: '/account/referrals', icon: '🎁', label: 'Referral Program' },
  { to: '/account/affiliate', icon: '🤝', label: 'Affiliate Partner' },
  { to: '/account/privacy', icon: '🔒', label: 'Privacy & GDPR' },
];
</script>
