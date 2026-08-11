<template>
  <div class="min-h-screen flex bg-slate-950 text-slate-100 font-sans antialiased">
    <!-- Complete Admin Sidebar (All 18+ Modules Restored) -->
    <aside class="w-64 bg-slate-900 border-r border-slate-800 flex flex-col shrink-0">
      <!-- Sidebar Header -->
      <div class="p-6 border-b border-slate-800 flex items-center justify-between">
        <div class="flex items-center gap-3">
          <img
            v-if="siteLogoUrl"
            :src="siteLogoUrl"
            :alt="siteName"
            class="w-9 h-9 object-contain rounded-xl border border-slate-700 bg-slate-950 p-1 shrink-0"
            @error="logoError = true"
          />
          <div v-if="!siteLogoUrl || logoError" class="w-9 h-9 bg-indigo-600 rounded-xl flex items-center justify-center font-black text-white text-base shadow-md shadow-indigo-500/20 uppercase shrink-0">
            {{ siteName ? siteName.charAt(0) : 'L' }}
          </div>
          <div class="min-w-0">
            <h2 class="font-bold text-white text-sm tracking-tight truncate">{{ siteName }} Admin</h2>
            <span class="text-[10px] text-indigo-400 font-mono block truncate">v1.0.0 • Control Panel</span>
          </div>
        </div>
      </div>

      <!-- Navigation Links (All Modules Preserved) -->
      <nav class="flex-1 px-3 py-4 space-y-1 overflow-y-auto text-xs font-medium">
        <router-link
          to="/"
          class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl transition-all text-slate-400 hover:text-white hover:bg-slate-800"
          active-class="bg-indigo-600 text-white font-bold shadow-md shadow-indigo-600/30"
        >
          <span>📊</span>
          <span>Dashboard</span>
        </router-link>

        <!-- CATALOG MANAGEMENT -->
        <div class="pt-4 pb-1 px-3">
          <span class="text-[10px] font-mono font-bold text-slate-500 uppercase tracking-widest">Catalog</span>
        </div>

        <router-link
          to="/catalog"
          class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl transition-all text-slate-400 hover:text-white hover:bg-slate-800"
          active-class="bg-indigo-600/20 text-indigo-300 font-bold border border-indigo-500/30"
        >
          <span>📦</span>
          <span>Catalog Management</span>
        </router-link>

        <router-link
          to="/flash-sales"
          class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl transition-all text-slate-400 hover:text-white hover:bg-slate-800"
          active-class="bg-indigo-600/20 text-indigo-300 font-bold border border-indigo-500/30"
        >
          <span>⚡</span>
          <span>Flash Sales</span>
        </router-link>

        <router-link
          to="/inventory"
          class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl transition-all text-slate-400 hover:text-white hover:bg-slate-800"
          active-class="bg-indigo-600/20 text-indigo-300 font-bold border border-indigo-500/30"
        >
          <span>📉</span>
          <span>Inventory & Stock</span>
        </router-link>

        <router-link
          to="/orders"
          class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl transition-all text-slate-400 hover:text-white hover:bg-slate-800"
          active-class="bg-indigo-600/20 text-indigo-300 font-bold border border-indigo-500/30"
        >
          <span>🛒</span>
          <span>Orders & Shipments</span>
        </router-link>

        <router-link
          to="/media"
          class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl transition-all text-slate-400 hover:text-white hover:bg-slate-800"
          active-class="bg-indigo-600/20 text-indigo-300 font-bold border border-indigo-500/30"
        >
          <span>🖼️</span>
          <span>Media Library</span>
        </router-link>

        <!-- MARKETING & CONTENT -->
        <div class="pt-4 pb-1 px-3">
          <span class="text-[10px] font-mono font-bold text-slate-500 uppercase tracking-widest">Marketing & Content</span>
        </div>

        <router-link
          to="/marketing"
          class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl transition-all text-slate-400 hover:text-white hover:bg-slate-800"
          active-class="bg-indigo-600/20 text-indigo-300 font-bold border border-indigo-500/30"
        >
          <span>🎯</span>
          <span>Marketing & Coupons</span>
        </router-link>

        <router-link
          to="/campaigns"
          class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl transition-all text-slate-400 hover:text-white hover:bg-slate-800"
          active-class="bg-indigo-600/20 text-indigo-300 font-bold border border-indigo-500/30"
        >
          <span>✉️</span>
          <span>Campaign Builder</span>
        </router-link>

        <router-link
          to="/content"
          class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl transition-all text-slate-400 hover:text-white hover:bg-slate-800"
          active-class="bg-indigo-600/20 text-indigo-300 font-bold border border-indigo-500/30"
        >
          <span>📝</span>
          <span>Content & Reviews</span>
        </router-link>

        <router-link
          to="/landing-builder"
          class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl transition-all text-slate-400 hover:text-white hover:bg-slate-800"
          active-class="bg-indigo-600/20 text-indigo-300 font-bold border border-indigo-500/30"
        >
          <span>🎨</span>
          <span>Landing Page Builder</span>
        </router-link>

        <router-link
          to="/seo"
          class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl transition-all text-slate-400 hover:text-white hover:bg-slate-800"
          active-class="bg-indigo-600/20 text-indigo-300 font-bold border border-indigo-500/30"
        >
          <span>🔍</span>
          <span>SEO & Redirects</span>
        </router-link>

        <!-- CUSTOMERS & OPS -->
        <div class="pt-4 pb-1 px-3">
          <span class="text-[10px] font-mono font-bold text-slate-500 uppercase tracking-widest">Customers & Ops</span>
        </div>

        <router-link
          to="/customers"
          class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl transition-all text-slate-400 hover:text-white hover:bg-slate-800"
          active-class="bg-indigo-600/20 text-indigo-300 font-bold border border-indigo-500/30"
        >
          <span>👥</span>
          <span>Customers List</span>
        </router-link>

        <!-- CONFIGURATION & SYSTEM -->
        <div class="pt-4 pb-1 px-3">
          <span class="text-[10px] font-mono font-bold text-slate-500 uppercase tracking-widest">Configuration & System</span>
        </div>

        <router-link
          to="/shipping"
          class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl transition-all text-slate-400 hover:text-white hover:bg-slate-800"
          active-class="bg-indigo-600/20 text-indigo-300 font-bold border border-indigo-500/30"
        >
          <span>🚚</span>
          <span>Shipping & Tax</span>
        </router-link>

        <router-link
          to="/settings"
          class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl transition-all text-slate-400 hover:text-white hover:bg-slate-800"
          active-class="bg-indigo-600/20 text-indigo-300 font-bold border border-indigo-500/30"
        >
          <span>⚙️</span>
          <span>System Settings</span>
        </router-link>

        <router-link
          to="/health"
          class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl transition-all text-slate-400 hover:text-white hover:bg-slate-800"
          active-class="bg-indigo-600/20 text-indigo-300 font-bold border border-indigo-500/30"
        >
          <span>🩺</span>
          <span>System Health</span>
        </router-link>

        <!-- SECURITY & ACCESS -->
        <div class="pt-4 pb-1 px-3">
          <span class="text-[10px] font-mono font-bold text-slate-500 uppercase tracking-widest">Security & RBAC</span>
        </div>

        <router-link
          to="/rbac"
          class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl transition-all text-slate-400 hover:text-white hover:bg-slate-800"
          active-class="bg-indigo-600/20 text-indigo-300 font-bold border border-indigo-500/30"
        >
          <span>🔑</span>
          <span>Roles & Permissions</span>
        </router-link>

        <router-link
          to="/security"
          class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl transition-all text-slate-400 hover:text-white hover:bg-slate-800"
          active-class="bg-indigo-600/20 text-indigo-300 font-bold border border-indigo-500/30"
        >
          <span>🛡️</span>
          <span>Security & Fraud</span>
        </router-link>

        <router-link
          to="/2fa-setup"
          class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl transition-all text-slate-400 hover:text-white hover:bg-slate-800"
          active-class="bg-indigo-600/20 text-indigo-300 font-bold border border-indigo-500/30"
        >
          <span>🔐</span>
          <span>2FA Setup Wizard</span>
        </router-link>
      </nav>

      <!-- User Profile Sidebar Footer -->
      <div class="p-4 border-t border-slate-800 bg-slate-900/50 flex items-center justify-between text-xs">
        <div class="flex items-center gap-2.5 truncate">
          <div class="w-8 h-8 rounded-xl bg-indigo-600 text-white font-bold text-xs flex items-center justify-center uppercase shrink-0">
            {{ authStore.user?.name?.charAt(0) || 'A' }}
          </div>
          <div class="truncate">
            <div class="font-bold text-white truncate">{{ authStore.user?.name || 'Admin User' }}</div>
            <div class="text-[10px] text-slate-400 truncate">{{ authStore.user?.email || 'admin@larashop.test' }}</div>
          </div>
        </div>

        <button @click="authStore.logout()" class="text-slate-400 hover:text-rose-400 transition-colors p-1" title="Logout">
          🚪
        </button>
      </div>
    </aside>

    <!-- Main Content Area -->
    <div class="flex-1 flex flex-col min-w-0">
      <!-- Top Bar -->
      <header class="h-16 bg-slate-900 border-b border-slate-800 px-8 flex items-center justify-between">
        <div class="font-bold text-white text-sm flex items-center gap-2">
          <span>⚙️</span>
          <span>System Command Center</span>
        </div>

        <div class="flex items-center gap-4">
          <a
            href="http://localhost:3000"
            target="_blank"
            class="px-4 py-2 bg-indigo-600/20 hover:bg-indigo-600 text-indigo-300 hover:text-white text-xs font-bold rounded-xl border border-indigo-500/30 transition-all flex items-center gap-1.5"
          >
            <span>Visit Storefront</span>
            <span>↗</span>
          </a>
        </div>
      </header>

      <!-- View Router View Container -->
      <main class="flex-1 p-8 overflow-y-auto bg-slate-950">
        <router-view />
      </main>
    </div>

    <!-- Floating Toast Notification System -->
    <AdminToastNotification />
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { useAdminAuthStore } from '../stores/auth';
import AdminToastNotification from '../components/AdminToastNotification.vue';
import api from '../services/api';

const authStore = useAdminAuthStore();

const siteName = ref('LaraShop');
const siteLogo = ref<string | null>(null);
const logoError = ref(false);

const siteLogoUrl = computed(() => {
  if (logoError.value || !siteLogo.value) return null;
  const logo = siteLogo.value;
  if (logo.startsWith('http://') || logo.startsWith('https://')) return logo;
  return `http://localhost:8000${logo.startsWith('/') ? '' : '/'}${logo}`;
});

onMounted(async () => {
  try {
    const res = await api.get('/settings/public');
    const data = res.data?.data || res.data;
    if (data && typeof data === 'object') {
      let name = data.site_name || data['general.site_name'];
      if (name) {
        name = String(name).trim().replace(/^"|"$/g, '');
        siteName.value = name;
      }
      let logo = data.logo_dark || data.logo_light || data.site_logo || data['general.logo_dark'] || data['general.logo_light'];
      if (logo) {
        logo = String(logo).trim().replace(/^"|"$/g, '');
        siteLogo.value = logo;
      }
    }
  } catch {}
});
</script>
