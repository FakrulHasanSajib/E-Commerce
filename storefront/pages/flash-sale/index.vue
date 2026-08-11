<template>
  <div class="space-y-6">
    <div class="bg-gradient-to-r from-rose-900 via-rose-800 to-slate-900 text-white p-8 rounded-3xl shadow-xl flex flex-col md:flex-row items-center justify-between gap-6">
      <div>
        <span class="px-3 py-1 bg-rose-500/30 text-rose-300 rounded-full text-xs font-bold uppercase tracking-widest border border-rose-400/20">
          Module 15 • Limited Time Offer
        </span>
        <h1 class="text-3xl font-black mt-2">Mega Flash Sales ⚡</h1>
        <p class="text-rose-200 text-xs mt-1">Exclusive discounts with real-time stock allocation locking.</p>
      </div>

      <FlashSaleTimer />
    </div>

    <!-- Items Grid -->
    <div v-if="pending" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
      <div v-for="i in 4" :key="i" class="h-64 bg-white rounded-2xl animate-pulse border border-slate-100"></div>
    </div>

    <div v-else-if="flashSales && flashSales.length > 0" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
      <ProductCard v-for="item in flashSales" :key="item.id" :product="item" />
    </div>

    <div v-else class="bg-white p-12 rounded-2xl border text-center text-slate-500 text-xs">
      No active flash sales right now. Check back soon!
    </div>
  </div>
</template>

<script setup lang="ts">
const { $api } = useNuxtApp();

const { data: flashSales, pending } = await useAsyncData('flash-sales-page', async () => {
  try {
    const res = await $api.get('/flash-sales');
    return res.data?.data || [];
  } catch (e) {
    return [];
  }
});
</script>
