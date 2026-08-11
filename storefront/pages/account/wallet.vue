<template>
  <div class="space-y-6">
    <div class="bg-gradient-to-r from-indigo-900 via-indigo-800 to-slate-900 text-white p-8 rounded-3xl shadow-xl flex items-center justify-between">
      <div>
        <span class="text-xs font-mono text-indigo-300 uppercase tracking-widest">My Digital Wallet</span>
        <h1 class="text-4xl font-black mt-2">৳{{ authStore.user?.wallet_balance || '0.00' }}</h1>
        <p class="text-indigo-200 text-xs mt-1">Store credit used automatically during checkout or refund credit.</p>
      </div>

      <div class="w-14 h-14 bg-indigo-500/20 text-indigo-300 rounded-2xl flex items-center justify-center text-3xl font-black border border-indigo-400/30">
        💳
      </div>
    </div>

    <!-- Wallet Transaction Ledger -->
    <div class="bg-white p-6 rounded-2xl border border-slate-100 shadow-xs space-y-4">
      <h3 class="font-bold text-slate-900 text-sm border-b border-slate-100 pb-3">Wallet Transaction Ledger</h3>

      <div v-if="pending" class="space-y-2">
        <div v-for="i in 3" :key="i" class="h-12 bg-slate-50 rounded-xl animate-pulse"></div>
      </div>

      <div v-else-if="transactions && transactions.length > 0" class="space-y-2">
        <div v-for="tx in transactions" :key="tx.id" class="flex items-center justify-between p-3 bg-slate-50 rounded-xl border border-slate-100 text-xs">
          <div>
            <div class="font-bold text-slate-900">{{ tx.description }}</div>
            <div class="text-slate-400 font-mono text-[10px]">{{ tx.created_at }}</div>
          </div>
          <div :class="['font-black text-sm', tx.type === 'credit' ? 'text-emerald-600' : 'text-rose-600']">
            {{ tx.type === 'credit' ? '+' : '-' }}৳{{ tx.amount }}
          </div>
        </div>
      </div>

      <div v-else class="text-center py-6 text-slate-400 text-xs">
        No wallet transactions recorded yet.
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { useAuthStore } from '~/stores/auth';
definePageMeta({ middleware: ['auth'], layout: 'account' });

const authStore = useAuthStore();
const { $api } = useNuxtApp();

const { data: transactions, pending } = await useAsyncData('customer-wallet-tx', async () => {
  try {
    const res = await $api.get('/customer/wallet/transactions');
    return res.data?.data || [];
  } catch (e) {
    return [];
  }
});
</script>

