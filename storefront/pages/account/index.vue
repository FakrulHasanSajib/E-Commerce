<template>
  <div class="space-y-6">
    <div class="bg-white p-6 rounded-2xl border border-slate-100 shadow-xs flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-black text-slate-900">My Account</h1>
        <p class="text-slate-500 text-xs mt-1">Manage profile, password, orders, and wallet balance.</p>
      </div>

      <div class="flex items-center gap-2">
        <NuxtLink to="/account/wallet" class="px-3 py-1.5 bg-indigo-50 text-indigo-600 rounded-xl text-xs font-bold border border-indigo-100">
          Wallet: ৳{{ authStore.user?.wallet_balance || '0.00' }}
        </NuxtLink>
      </div>
    </div>

    <!-- Account Sub-navigation Tabs -->
    <div class="flex items-center gap-2 overflow-x-auto pb-2 border-b border-slate-200 text-xs font-bold">
      <NuxtLink to="/account" class="px-4 py-2 bg-indigo-600 text-white rounded-xl shadow-xs">Profile & Security</NuxtLink>
      <NuxtLink to="/account/orders" class="px-4 py-2 bg-white text-slate-700 hover:bg-slate-100 rounded-xl">Orders</NuxtLink>
      <NuxtLink to="/account/addresses" class="px-4 py-2 bg-white text-slate-700 hover:bg-slate-100 rounded-xl">Address Book</NuxtLink>
      <NuxtLink to="/account/wishlist" class="px-4 py-2 bg-white text-slate-700 hover:bg-slate-100 rounded-xl">Wishlist</NuxtLink>
      <NuxtLink to="/account/wallet" class="px-4 py-2 bg-white text-slate-700 hover:bg-slate-100 rounded-xl">Wallet</NuxtLink>
      <NuxtLink to="/account/loyalty" class="px-4 py-2 bg-white text-slate-700 hover:bg-slate-100 rounded-xl">Loyalty Points</NuxtLink>
      <NuxtLink to="/account/returns" class="px-4 py-2 bg-white text-slate-700 hover:bg-slate-100 rounded-xl">Returns</NuxtLink>
      <NuxtLink to="/account/referrals" class="px-4 py-2 bg-white text-slate-700 hover:bg-slate-100 rounded-xl">Referrals</NuxtLink>
      <NuxtLink to="/account/privacy" class="px-4 py-2 bg-white text-slate-700 hover:bg-slate-100 rounded-xl">GDPR Privacy</NuxtLink>
      <NuxtLink to="/account/affiliate" class="px-4 py-2 bg-white text-slate-700 hover:bg-slate-100 rounded-xl">Affiliate</NuxtLink>
    </div>

    <!-- Profile Edit & Password Update Forms -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
      <!-- Edit Profile Form -->
      <div class="bg-white p-6 rounded-2xl border border-slate-100 shadow-xs space-y-4">
        <h3 class="font-bold text-slate-900 text-sm border-b border-slate-100 pb-3">Update Profile</h3>

        <form @submit.prevent="updateProfile" class="space-y-3">
          <div>
            <label class="block text-xs font-bold text-slate-700 mb-1">Full Name</label>
            <input v-model="profileForm.name" type="text" required class="w-full px-3 py-2 bg-slate-50 border rounded-xl text-xs" />
          </div>
          <div>
            <label class="block text-xs font-bold text-slate-700 mb-1">Email Address</label>
            <input v-model="profileForm.email" type="email" required class="w-full px-3 py-2 bg-slate-50 border rounded-xl text-xs" />
          </div>
          <button type="submit" class="px-4 py-2 bg-indigo-600 text-white text-xs font-bold rounded-xl shadow-xs">
            Save Profile Changes
          </button>
        </form>
      </div>

      <!-- Change Password Form -->
      <div class="bg-white p-6 rounded-2xl border border-slate-100 shadow-xs space-y-4">
        <h3 class="font-bold text-slate-900 text-sm border-b border-slate-100 pb-3">Change Password</h3>

        <form @submit.prevent="updatePassword" class="space-y-3">
          <div>
            <label class="block text-xs font-bold text-slate-700 mb-1">Current Password</label>
            <input v-model="passwordForm.current_password" type="password" required class="w-full px-3 py-2 bg-slate-50 border rounded-xl text-xs" />
          </div>
          <div>
            <label class="block text-xs font-bold text-slate-700 mb-1">New Password</label>
            <input v-model="passwordForm.password" type="password" required class="w-full px-3 py-2 bg-slate-50 border rounded-xl text-xs" />
          </div>
          <div>
            <label class="block text-xs font-bold text-slate-700 mb-1">Confirm New Password</label>
            <input v-model="passwordForm.password_confirmation" type="password" required class="w-full px-3 py-2 bg-slate-50 border rounded-xl text-xs" />
          </div>
          <button type="submit" class="px-4 py-2 bg-slate-900 text-white text-xs font-bold rounded-xl shadow-xs">
            Update Password
          </button>
        </form>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { useAuthStore } from '~/stores/auth';

definePageMeta({ middleware: ['auth'], layout: 'account' });

const authStore = useAuthStore();
const { $api } = useNuxtApp();

const profileForm = reactive({
  name: authStore.user?.name || '',
  email: authStore.user?.email || '',
});

const passwordForm = reactive({
  current_password: '',
  password: '',
  password_confirmation: '',
});

async function updateProfile() {
  try {
    await $api.put('/customer/profile', profileForm);
    alert('Profile updated successfully!');
    await authStore.fetchCurrentUser();
  } catch (e: any) {
    alert(e.response?.data?.message || 'Failed to update profile.');
  }
}

async function updatePassword() {
  try {
    await $api.put('/customer/password', passwordForm);
    alert('Password updated successfully!');
    passwordForm.current_password = '';
    passwordForm.password = '';
    passwordForm.password_confirmation = '';
  } catch (e: any) {
    alert(e.response?.data?.message || 'Failed to update password.');
  }
}
</script>

