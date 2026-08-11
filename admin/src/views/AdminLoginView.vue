<template>
  <div class="min-h-screen flex items-center justify-center bg-slate-950 p-6">
    <div class="w-full max-w-md bg-slate-900 border border-slate-800 p-8 rounded-2xl shadow-2xl">
      <!-- Logo Header -->
      <div class="text-center mb-8">
        <div class="w-12 h-12 bg-indigo-600 rounded-2xl flex items-center justify-center font-black text-white text-2xl mx-auto mb-3 shadow-lg shadow-indigo-600/30">
          L
        </div>
        <h1 class="text-2xl font-black text-white tracking-tight">Admin Portal</h1>
        <p class="text-slate-400 text-xs mt-1">LaraShop Control Center</p>
      </div>

      <!-- Validation Error Alert -->
      <div v-if="errorMessage" class="mb-6 p-4 bg-rose-500/10 border border-rose-500/30 text-rose-300 rounded-xl text-xs font-medium">
        {{ errorMessage }}
      </div>

      <!-- Step 1: Username & Password Login Form -->
      <form v-if="!authStore.requires2FA" @submit.prevent="handleInitialLogin" class="space-y-4">
        <div>
          <label class="block text-[11px] font-mono uppercase tracking-wider text-slate-400 mb-1">Admin Email</label>
          <input
            v-model="email"
            type="email"
            required
            placeholder="admin@larashop.test"
            class="w-full px-4 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-sm text-white focus:outline-hidden focus:border-indigo-500 transition-all"
          />
        </div>

        <div>
          <label class="block text-[11px] font-mono uppercase tracking-wider text-slate-400 mb-1">Password</label>
          <input
            v-model="password"
            type="password"
            required
            placeholder="••••••••"
            class="w-full px-4 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-sm text-white focus:outline-hidden focus:border-indigo-500 transition-all"
          />
        </div>

        <button
          type="submit"
          :disabled="authStore.isLoading"
          class="w-full py-3 bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-sm rounded-xl shadow-lg shadow-indigo-600/20 transition-all disabled:opacity-50 flex items-center justify-center gap-2"
        >
          <span v-if="authStore.isLoading" class="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
          <span>Authenticate Admin</span>
        </button>
      </form>

      <!-- Step 2: Multi-step 2FA TOTP / Recovery Code Challenge -->
      <form v-else @submit.prevent="handle2FAVerify" class="space-y-4">
        <div class="p-4 bg-indigo-500/10 border border-indigo-500/20 rounded-xl text-center mb-4">
          <span class="text-2xl mb-1 block">🔐</span>
          <h3 class="font-bold text-sm text-white">Two-Factor Challenge</h3>
          <p class="text-xs text-indigo-300 mt-1">Enter 6-digit TOTP from your authenticator app or single-use recovery code.</p>
        </div>

        <div>
          <label class="block text-[11px] font-mono uppercase tracking-wider text-slate-400 mb-1">6-Digit Code / Recovery Code</label>
          <input
            v-model="totpCode"
            type="text"
            required
            placeholder="123456"
            class="w-full px-4 py-3 bg-slate-950 border border-indigo-500 rounded-xl text-center text-xl font-mono text-white tracking-widest focus:outline-hidden"
          />
        </div>

        <button
          type="submit"
          :disabled="authStore.isLoading"
          class="w-full py-3 bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-sm rounded-xl shadow-lg shadow-emerald-600/20 transition-all disabled:opacity-50 flex items-center justify-center gap-2"
        >
          <span v-if="authStore.isLoading" class="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
          <span>Verify & Unlock</span>
        </button>
      </form>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import { useAdminAuthStore } from '../stores/auth';

const router = useRouter();
const authStore = useAdminAuthStore();

const email = ref('admin@larashop.test');
const password = ref('password');
const totpCode = ref('');
const errorMessage = ref('');

async function handleInitialLogin() {
  errorMessage.value = '';
  try {
    const res = await authStore.login({ email: email.value, password: password.value });
    if (!res.requires_2fa) {
      router.push('/');
    }
  } catch (err: any) {
    errorMessage.value = err.response?.data?.message || 'Invalid admin credentials.';
  }
}

async function handle2FAVerify() {
  errorMessage.value = '';
  try {
    await authStore.verify2FA(totpCode.value);
    router.push('/');
  } catch (err: any) {
    errorMessage.value = err.response?.data?.message || 'Invalid 2FA code.';
  }
}
</script>
