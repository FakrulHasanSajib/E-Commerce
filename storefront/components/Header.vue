<template>
  <header class="w-full sticky top-0 z-40 bg-white/90 backdrop-blur-md border-b border-slate-100 shadow-xs">
    <!-- Top Announcement Ticker Bar -->
    <div
      v-if="settingsStore.settings.announcement_bar_enabled && settingsStore.settings.announcement_bar_text"
      class="bg-gradient-to-r from-indigo-700 via-purple-700 to-indigo-800 text-white text-xs py-2 px-4 text-center font-bold tracking-wide flex items-center justify-center gap-2 shadow-xs"
    >
      <span>⚡ {{ settingsStore.settings.announcement_bar_text }}</span>
    </div>

    <!-- Top Utility Bar -->
    <div class="bg-slate-950 text-slate-400 text-[11px] py-2 px-4 md:px-8 border-b border-slate-900">
      <div class="max-w-7xl mx-auto flex items-center justify-between">
        <div class="flex items-center gap-5">
          <span class="flex items-center gap-1.5 font-medium"><span class="text-indigo-400">📞</span> {{ headerPhone }}</span>
          <span class="hidden md:flex items-center gap-1.5 font-medium"><span class="text-indigo-400">✉️</span> {{ headerEmail }}</span>
          <span class="hidden lg:inline text-emerald-400 font-bold">✓ {{ headerGuaranteeText }}</span>
        </div>

        <div class="flex items-center gap-4">
          <div class="flex items-center gap-1 cursor-pointer hover:text-white transition-colors">
            <span class="font-bold text-indigo-400">🌐 EN</span>
            <span class="text-slate-600">|</span>
            <span class="hover:text-indigo-300">BN</span>
          </div>

          <div class="flex items-center gap-1 bg-slate-900 px-2.5 py-0.5 rounded-lg border border-slate-800 text-amber-400 font-mono font-bold text-[10px]">
            <span>{{ settingsStore.settings.currency }} ({{ settingsStore.settings.currency_symbol }})</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Main Navigation Header -->
    <div class="max-w-7xl mx-auto px-4 md:px-8 py-3.5 flex items-center justify-between gap-6">
      <!-- Logo -->
      <NuxtLink to="/" class="flex items-center gap-2.5 text-2xl font-black text-slate-900 tracking-tight group">
        <img
          v-if="siteLogoUrl"
          :src="siteLogoUrl"
          :alt="settingsStore.settings.site_name"
          class="h-10 max-w-[180px] object-contain transition-transform group-hover:scale-105"
          @error="logoLoadError = true"
        />
        <div v-if="!siteLogoUrl || logoLoadError" class="w-10 h-10 rounded-2xl bg-gradient-to-tr from-indigo-600 to-violet-500 flex items-center justify-center text-white font-black shadow-lg shadow-indigo-500/25 group-hover:scale-105 transition-all">
          {{ settingsStore.settings.site_name ? settingsStore.settings.site_name.charAt(0).toUpperCase() : 'L' }}
        </div>
        <span class="font-heading tracking-tight bg-gradient-to-r from-slate-900 via-indigo-950 to-slate-900 bg-clip-text text-transparent">
          {{ settingsStore.settings.site_name || 'LaraShop' }}
        </span>
      </NuxtLink>

      <!-- Search Input with 300ms Debounce -->
      <div class="flex-1 max-w-xl relative hidden md:block">
        <div class="relative flex items-center">
          <input
            v-model="searchQuery"
            type="text"
            placeholder="Search products, categories, brands..."
            class="w-full pl-11 pr-24 py-2.5 bg-slate-100/80 border border-slate-200/80 rounded-2xl text-xs font-medium focus:outline-none focus:border-indigo-500 focus:bg-white focus:ring-4 focus:ring-indigo-500/10 transition-all"
            @input="handleSearchInput"
          />
          <span class="absolute left-3.5 text-slate-400 text-base">🔍</span>
          <button @click="executeSearch" class="absolute right-1.5 px-3 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-xl shadow-xs transition-all">
            Search
          </button>
        </div>
      </div>

      <!-- Actions (Wishlist, Cart, Account) -->
      <div class="flex items-center gap-3">
        <!-- Compare Icon -->
        <NuxtLink to="/compare" class="p-2.5 rounded-2xl text-slate-600 hover:text-indigo-600 hover:bg-slate-100 transition-all relative" title="Product Comparison">
          <span class="text-xl">⚖️</span>
        </NuxtLink>

        <!-- Wishlist Badge -->
        <NuxtLink to="/account/wishlist" class="p-2.5 rounded-2xl text-slate-600 hover:text-rose-600 hover:bg-rose-50 transition-all relative" title="Wishlist">
          <span class="text-xl">❤️</span>
        </NuxtLink>

        <!-- Cart Badge with Mini Total -->
        <NuxtLink to="/cart" class="flex items-center gap-2 p-2 rounded-2xl bg-indigo-50 hover:bg-indigo-100 border border-indigo-100 transition-all relative group">
          <div class="relative">
            <span class="text-xl">🛒</span>
            <span v-if="cartStore.totalItems > 0" class="absolute -top-2 -right-2 w-5 h-5 bg-gradient-to-r from-indigo-600 to-purple-600 text-white rounded-full text-[10px] font-black flex items-center justify-center shadow-md shadow-indigo-500/30 animate-pulse">
              {{ cartStore.totalItems }}
            </span>
          </div>
          <div class="hidden sm:block text-left pr-1">
            <div class="text-[9px] font-mono uppercase text-indigo-400 font-bold">My Cart</div>
            <div class="text-xs font-black text-indigo-900">৳{{ cartStore.subtotal }}</div>
          </div>
        </NuxtLink>

        <!-- Account Menu / Login Button -->
        <div v-if="authStore.isAuthenticated" class="relative group">
          <button class="flex items-center gap-2 py-2 px-3 bg-slate-900 text-white hover:bg-slate-800 rounded-2xl text-xs font-bold transition-all shadow-md">
            <div class="w-6 h-6 rounded-full bg-gradient-to-r from-indigo-500 to-purple-500 text-white text-[11px] flex items-center justify-center uppercase font-black">
              {{ authStore.user?.name?.charAt(0) || 'U' }}
            </div>
            <span>{{ authStore.user?.name }}</span>
            <span class="text-[10px] opacity-60">▼</span>
          </button>

          <!-- Account Dropdown -->
          <div class="absolute right-0 top-full mt-2 w-52 bg-slate-900 text-slate-200 rounded-2xl shadow-2xl border border-slate-800 py-2 hidden group-hover:block z-50 text-xs">
            <div class="px-4 py-2 border-b border-slate-800">
              <div class="font-bold text-white truncate">{{ authStore.user?.name }}</div>
              <div class="text-[10px] text-slate-400 truncate">{{ authStore.user?.email }}</div>
            </div>
            <NuxtLink to="/account" class="block px-4 py-2 hover:bg-slate-800 hover:text-white transition-colors">👤 My Profile</NuxtLink>
            <NuxtLink to="/account/orders" class="block px-4 py-2 hover:bg-slate-800 hover:text-white transition-colors">📦 My Orders</NuxtLink>
            <NuxtLink to="/account/wallet" class="block px-4 py-2 hover:bg-slate-800 hover:text-emerald-400 transition-colors">💳 Wallet (৳{{ authStore.user?.wallet_balance || '0.00' }})</NuxtLink>
            <NuxtLink to="/account/loyalty" class="block px-4 py-2 hover:bg-slate-800 hover:text-amber-400 transition-colors">⭐ Loyalty Points</NuxtLink>
            <hr class="my-1 border-slate-800" />
            <button @click="authStore.logout()" class="w-full text-left px-4 py-2 text-rose-400 hover:bg-rose-950/50 font-bold transition-colors">🚪 Logout</button>
          </div>
        </div>

        <NuxtLink
          v-else
          to="/login"
          class="px-5 py-2.5 bg-gradient-to-r from-indigo-600 to-indigo-700 hover:from-indigo-700 hover:to-indigo-800 text-white text-xs font-bold rounded-2xl shadow-lg shadow-indigo-500/25 transition-all flex items-center gap-1.5"
        >
          <span>Sign In</span>
          <span>→</span>
        </NuxtLink>
      </div>
    </div>

    <!-- Category Mega Menu Navigation Bar -->
    <div class="bg-slate-900 border-t border-slate-800 text-white">
      <CategoryMegaMenu />
    </div>
  </header>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue';
import { useSettingsStore } from '~/stores/settings';
import { useAuthStore } from '~/stores/auth';
import { useCartStore } from '~/stores/cart';

const settingsStore = useSettingsStore();
const authStore = useAuthStore();
const cartStore = useCartStore();

const logoLoadError = ref(false);

const siteLogoUrl = computed(() => {
  if (logoLoadError.value) return null;
  const logo = settingsStore.settings.logo_light || settingsStore.settings.site_logo || settingsStore.settings.logo_dark;
  if (!logo) return null;
  if (logo.startsWith('http://') || logo.startsWith('https://')) return logo;
  return `http://localhost:8000${logo.startsWith('/') ? '' : '/'}${logo}`;
});

const headerPhone = computed(() => {
  return settingsStore.settings.phone || settingsStore.settings.contact_info_phone || '+880 1700-000000';
});

const headerEmail = computed(() => {
  return settingsStore.settings.email || settingsStore.settings.contact_info_email || 'support@larashop.com';
});

const headerGuaranteeText = computed(() => {
  return settingsStore.settings.guarantee_text || '100% Genuine Guaranteed';
});

const searchQuery = ref('');
let debounceTimer: ReturnType<typeof setTimeout> | null = null;

function handleSearchInput() {
  if (debounceTimer) clearTimeout(debounceTimer);
  debounceTimer = setTimeout(() => {
    executeSearch();
  }, 300);
}

function executeSearch() {
  if (searchQuery.value.trim().length >= 2) {
    navigateTo({ path: '/search', query: { q: searchQuery.value } });
  }
}
</script>
