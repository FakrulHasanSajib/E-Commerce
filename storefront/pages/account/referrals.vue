<template>
  <div class="space-y-6">
    <div class="bg-gradient-to-r from-purple-900 via-purple-800 to-slate-900 text-white p-8 rounded-3xl shadow-xl space-y-4">
      <span class="text-xs font-mono text-purple-300 uppercase tracking-widest">Referral Program</span>
      <h1 class="text-2xl font-black">Invite Friends & Earn Wallet Credit 🎁</h1>

      <div class="flex items-center gap-3 p-3 bg-slate-900/80 rounded-xl border border-purple-500/30 max-w-md">
        <span class="text-xs font-mono text-purple-300 font-bold truncate flex-1">{{ referralLink }}</span>
        <button @click="copyLink" class="px-3 py-1 bg-purple-600 hover:bg-purple-700 text-white font-bold text-xs rounded-lg shrink-0">
          Copy Link
        </button>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { useAuthStore } from '~/stores/auth';
definePageMeta({ middleware: ['auth'], layout: 'account' });

const authStore = useAuthStore();
const referralLink = computed(() => `http://localhost:3000/register?ref=${authStore.user?.id || 1}`);

function copyLink() {
  navigator.clipboard.writeText(referralLink.value);
  alert('Referral link copied to clipboard!');
}
</script>

