<template>
  <div class="space-y-6">
    <div class="bg-white p-6 rounded-2xl border border-slate-100 shadow-xs flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-black text-slate-900">Affiliate Partner Dashboard</h1>
        <p class="text-slate-500 text-xs mt-1">Earn commission on referred customer sales.</p>
      </div>

      <button v-if="!affiliateData" @click="registerAffiliate" class="px-4 py-2 bg-indigo-600 text-white font-bold text-xs rounded-xl shadow-xs">
        Apply for Affiliate Program
      </button>
    </div>

    <!-- Affiliate Earnings Dashboard -->
    <div v-if="affiliateData" class="grid grid-cols-1 md:grid-cols-3 gap-6">
      <div class="bg-slate-900 p-6 rounded-2xl border border-slate-800 text-white">
        <div class="text-xs font-mono text-slate-400 uppercase">Commission Earnings</div>
        <div class="text-3xl font-black text-emerald-400 mt-2">৳{{ affiliateData.earnings || '0.00' }}</div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
definePageMeta({ middleware: ['auth'], layout: 'account' });
const { $api } = useNuxtApp();

const { data: affiliateData, refresh } = await useAsyncData('customer-affiliate-data', async () => {
  try {
    const res = await $api.get('/customer/affiliate');
    return res.data?.data || null;
  } catch (e) {
    return null;
  }
});

async function registerAffiliate() {
  try {
    await $api.post('/customer/affiliate/register');
    alert('Affiliate partner application submitted!');
    refresh();
  } catch (e: any) {
    alert(e.response?.data?.message || 'Failed to submit application.');
  }
}
</script>

