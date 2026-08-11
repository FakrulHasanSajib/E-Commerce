<template>
  <div class="space-y-6">
    <div class="bg-white p-6 rounded-2xl border border-slate-100 shadow-xs">
      <h1 class="text-2xl font-black text-slate-900">GDPR Data & Privacy Control</h1>
      <p class="text-slate-500 text-xs mt-1">Download your personal data export or request account deletion.</p>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
      <!-- Data Export Card -->
      <div class="bg-white p-6 rounded-2xl border border-slate-100 shadow-xs space-y-4">
        <div class="w-12 h-12 rounded-2xl bg-indigo-100 text-indigo-600 flex items-center justify-center text-2xl font-bold">
          📦
        </div>
        <h3 class="font-bold text-slate-900 text-base">Export Personal Data</h3>
        <p class="text-xs text-slate-500">Download a complete JSON export of your personal information, saved addresses, order history, and wallet transactions.</p>

        <button @click="exportData" class="px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs rounded-xl shadow-md transition-all">
          Download Personal Data Archive
        </button>
      </div>

      <!-- Account Deletion Card -->
      <div class="bg-white p-6 rounded-2xl border border-rose-100 shadow-xs space-y-4">
        <div class="w-12 h-12 rounded-2xl bg-rose-100 text-rose-600 flex items-center justify-center text-2xl font-bold">
          🗑️
        </div>
        <h3 class="font-bold text-rose-900 text-base">Anonymize / Delete Account</h3>
        <p class="text-xs text-slate-500">Permanently remove your account and anonymize order transactions in compliance with GDPR guidelines.</p>

        <button @click="showDeleteModal = true" class="px-4 py-2.5 bg-rose-600 hover:bg-rose-700 text-white font-bold text-xs rounded-xl shadow-md transition-all">
          Delete My Account
        </button>
      </div>
    </div>

    <!-- Password Confirmation Modal for Deletion -->
    <div v-if="showDeleteModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 p-4">
      <div class="bg-white p-6 rounded-2xl max-w-md w-full space-y-4">
        <h3 class="font-bold text-rose-600 text-base">Confirm Account Deletion</h3>
        <p class="text-xs text-slate-500">Enter your current account password to confirm permanent deletion.</p>

        <div>
          <label class="block text-xs font-bold text-slate-700 mb-1">Account Password</label>
          <input v-model="deletePassword" type="password" required class="w-full px-3 py-2 bg-slate-50 border rounded-xl text-xs" />
        </div>

        <div class="flex gap-2 justify-end">
          <button @click="showDeleteModal = false" class="px-4 py-2 text-xs font-bold text-slate-500">Cancel</button>
          <button @click="confirmDelete" class="px-4 py-2 bg-rose-600 text-white text-xs font-bold rounded-xl">Permanently Delete</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { useAuthStore } from '~/stores/auth';
definePageMeta({ middleware: ['auth'], layout: 'account' });

const authStore = useAuthStore();
const { $api } = useNuxtApp();

const showDeleteModal = ref(false);
const deletePassword = ref('');

async function exportData() {
  try {
    const res = await $api.get('/customer/gdpr/export');
    const blob = new Blob([JSON.stringify(res.data, null, 2)], { type: 'application/json' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `larashop_data_export_${authStore.user?.id}.json`;
    a.click();
  } catch (e) {
    alert('Failed to export data.');
  }
}

async function confirmDelete() {
  try {
    await $api.post('/customer/gdpr/delete', { password: deletePassword.value });
    alert('Account anonymized and deleted.');
    await authStore.logout();
  } catch (e: any) {
    alert(e.response?.data?.message || 'Failed to delete account.');
  }
}
</script>

