<template>
  <div class="max-w-md mx-auto my-12 bg-white p-8 rounded-3xl border border-slate-100 shadow-xl text-center space-y-6">
    <div class="w-16 h-16 bg-emerald-100 text-emerald-600 rounded-full flex items-center justify-center text-3xl mx-auto font-bold">
      ✓
    </div>

    <div>
      <h1 class="text-2xl font-black text-slate-900">Order Confirmed!</h1>
      <p class="text-slate-500 text-xs mt-1">Thank you for shopping with LaraShop.</p>
    </div>

    <div class="p-4 bg-slate-50 rounded-2xl border border-slate-100 font-mono text-xs text-slate-700">
      Order Number: <strong class="text-slate-900 font-bold">{{ route.params.orderNumber }}</strong>
    </div>

    <div class="pt-2 flex flex-col gap-3">
      <NuxtLink :to="`/orders/track/${route.params.orderNumber}`" class="py-3 bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs rounded-xl shadow-md">
        Track Order Status
      </NuxtLink>
      <NuxtLink to="/products" class="py-2.5 bg-slate-100 text-slate-700 font-bold text-xs rounded-xl hover:bg-slate-200">
        Back to Home
      </NuxtLink>
    </div>
  </div>
</template>

<script setup lang="ts">
import { useRoute } from 'vue-router';
const route = useRoute();
</script>
