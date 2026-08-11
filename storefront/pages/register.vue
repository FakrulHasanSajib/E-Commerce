<template>
  <div class="max-w-md mx-auto my-12 bg-white p-8 rounded-2xl shadow-xl border border-slate-100">
    <div class="text-center mb-8">
      <h1 class="text-2xl font-black text-slate-900">Create Account</h1>
      <p class="text-slate-500 text-sm mt-1">Join LaraShop today</p>
    </div>

    <!-- Error Alert -->
    <div v-if="errorMessage" class="mb-6 p-4 bg-rose-50 border border-rose-200 text-rose-700 rounded-xl text-sm font-medium">
      {{ errorMessage }}
    </div>

    <form @submit.prevent="handleRegister" class="space-y-4">
      <div>
        <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1">Full Name</label>
        <input
          v-model="form.name"
          type="text"
          required
          placeholder="Karim Hasan"
          class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-hidden focus:border-indigo-500 focus:bg-white transition-all"
        />
      </div>

      <div>
        <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1">Email Address</label>
        <input
          v-model="form.email"
          type="email"
          required
          placeholder="karim@test.com"
          class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-hidden focus:border-indigo-500 focus:bg-white transition-all"
        />
      </div>

      <div>
        <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1">Password</label>
        <input
          v-model="form.password"
          type="password"
          required
          placeholder="••••••••"
          class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-hidden focus:border-indigo-500 focus:bg-white transition-all"
        />
      </div>

      <div>
        <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1">Confirm Password</label>
        <input
          v-model="form.password_confirmation"
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
        <span>Register Account</span>
      </button>
    </form>

    <div class="mt-6 text-center text-xs text-slate-500">
      Already have an account?
      <NuxtLink to="/login" class="text-indigo-600 font-bold hover:underline">Sign In</NuxtLink>
    </div>
  </div>
</template>

<script setup lang="ts">
import { useAuthStore } from '~/stores/auth';

definePageMeta({
  middleware: ['guest'],
});

const authStore = useAuthStore();
const form = reactive({
  name: '',
  email: '',
  password: '',
  password_confirmation: '',
});
const errorMessage = ref('');

async function handleRegister() {
  errorMessage.value = '';
  try {
    await authStore.register(form);
    navigateTo('/');
  } catch (err: any) {
    errorMessage.value = err.response?.data?.message || 'Registration failed. Please check validation rules.';
  }
}
</script>
