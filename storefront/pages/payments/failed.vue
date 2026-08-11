<template>
  <div class="max-w-md mx-auto my-12 bg-white p-8 rounded-3xl border border-slate-100 shadow-xl text-center space-y-6">
    <div class="w-16 h-16 bg-rose-100 text-rose-600 rounded-full flex items-center justify-center text-3xl mx-auto font-bold">
      ✕
    </div>

    <div>
      <h1 class="text-2xl font-black text-slate-900">Payment Failed</h1>
      <p class="text-slate-500 text-xs mt-1">Your payment could not be processed by the payment gateway.</p>
    </div>

    <div class="p-4 bg-rose-50 border border-rose-100 rounded-2xl text-xs text-rose-700 font-medium">
      Reason: {{ route.query.message || 'Transaction declined or canceled by bank gateway.' }}
    </div>

    <div class="pt-2 flex flex-col gap-3">
      <NuxtLink to="/checkout" class="py-3 bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs rounded-xl shadow-md">
        Retry Payment
      </NuxtLink>
      <NuxtLink to="/cart" class="py-2.5 bg-slate-100 text-slate-700 font-bold text-xs rounded-xl hover:bg-slate-200">
        Return to Cart
      </NuxtLink>
    </div>
  </div>
</template>

<script setup lang="ts">
import { useRoute } from 'vue-router';
const route = useRoute();
</script>
