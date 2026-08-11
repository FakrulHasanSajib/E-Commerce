<template>
  <div class="space-y-6">
    <div class="bg-gradient-to-r from-amber-900 via-amber-800 to-slate-900 text-white p-8 rounded-3xl shadow-xl flex items-center justify-between">
      <div>
        <span class="text-xs font-mono text-amber-300 uppercase tracking-widest">Reward Loyalty Points</span>
        <h1 class="text-4xl font-black mt-2">{{ loyaltyData?.points || authStore.user?.loyalty_points || 0 }} pts</h1>
        <p class="text-amber-200 text-xs mt-1">Earn points on every delivered purchase. Convert 100 points to ৳10 wallet credit.</p>
      </div>

      <button @click="redeemPoints" class="px-6 py-3 bg-amber-500 hover:bg-amber-600 text-slate-900 font-black text-xs rounded-xl shadow-lg transition-all">
        Redeem to Wallet
      </button>
    </div>

    <!-- Points Ledger -->
    <div class="bg-white p-6 rounded-2xl border border-slate-100 shadow-xs space-y-4">
      <h3 class="font-bold text-slate-900 text-sm border-b border-slate-100 pb-3">Loyalty Points History</h3>

      <div v-if="pending" class="space-y-2">
        <div v-for="i in 2" :key="i" class="h-12 bg-slate-50 rounded-xl animate-pulse"></div>
      </div>

      <div v-else-if="loyaltyData?.transactions && loyaltyData.transactions.length > 0" class="space-y-2">
        <div v-for="tx in loyaltyData.transactions" :key="tx.id" class="flex items-center justify-between p-3 bg-slate-50 rounded-xl border text-xs">
          <div>
            <div class="font-bold text-slate-900">{{ tx.description }}</div>
            <div class="text-slate-400 font-mono text-[10px]">{{ tx.created_at }}</div>
          </div>
          <div class="font-black text-amber-600 text-sm">+{{ tx.points }} pts</div>
        </div>
      </div>

      <div v-else class="text-center py-6 text-slate-400 text-xs">
        No loyalty points history recorded yet.
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { useAuthStore } from '~/stores/auth';
definePageMeta({ middleware: ['auth'], layout: 'account' });

const authStore = useAuthStore();
const { $api } = useNuxtApp();

const { data: loyaltyData, pending, refresh } = await useAsyncData('customer-loyalty-data', async () => {
  try {
    const res = await $api.get('/customer/loyalty');
    return res.data;
  } catch (e) {
    return { points: 0, transactions: [] };
  }
});

async function redeemPoints() {
  try {
    const res = await $api.post('/customer/loyalty/redeem');
    alert(res.data?.message || 'Points redeemed to wallet successfully!');
    await authStore.fetchCurrentUser();
    refresh();
  } catch (e: any) {
    alert(e.response?.data?.message || 'Failed to redeem points.');
  }
}
</script>

