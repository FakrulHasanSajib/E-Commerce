<template>
  <div class="max-w-md mx-auto my-12 bg-white p-8 rounded-2xl shadow-xl border border-slate-100">
    <div class="text-center mb-8">
      <h1 class="text-2xl font-black text-slate-900">Welcome Back</h1>
      <p class="text-slate-500 text-sm mt-1">Sign in to your LaraShop account</p>
    </div>

    <!-- Validation Error Alert -->
    <div v-if="errorMessage" class="mb-6 p-4 bg-rose-50 border border-rose-200 text-rose-700 rounded-xl text-sm font-medium">
      {{ errorMessage }}
    </div>

    <form @submit.prevent="handleLogin" class="space-y-4">
      <div>
        <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1">Email Address</label>
        <input
          v-model="email"
          type="email"
          required
          placeholder="your@email.com"
          class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-hidden focus:border-indigo-500 focus:bg-white transition-all"
        />
      </div>

      <div>
        <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1">Password</label>
        <input
          v-model="password"
          type="password"
          required
          placeholder="••••••••"
          class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-hidden focus:border-indigo-500 focus:bg-white transition-all"
        />
      </div>

      <button
        type="submit"
        :disabled="authStore.isLoading"
        class="w-full py-3 bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-sm rounded-xl shadow-md transition-all disabled:opacity-50 flex items-center justify-center gap-2"
      >
        <span v-if="authStore.isLoading" class="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
        <span>Sign In</span>
      </button>
    </form>

    <div class="mt-6 text-center text-xs text-slate-500">
      Don't have an account?
      <NuxtLink to="/register" class="text-indigo-600 font-bold hover:underline">Create one</NuxtLink>
    </div>
  </div>
</template>

<script setup lang="ts">
import { useAuthStore } from '~/stores/auth';

definePageMeta({
  middleware: ['guest'],
});

const authStore = useAuthStore();
const email = ref('customer@larashop.test');
const password = ref('password123');
const errorMessage = ref('');

async function handleLogin() {
  errorMessage.value = '';
  try {
    await authStore.login({ email: email.value, password: password.value });
    navigateTo('/');
  } catch (err: any) {
    errorMessage.value = err.response?.data?.message || 'Invalid login credentials.';
  }
}
</script>
