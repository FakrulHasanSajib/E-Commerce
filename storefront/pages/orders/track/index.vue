<template>
  <div class="max-w-md mx-auto my-12 bg-white p-8 rounded-3xl border border-slate-100 shadow-xl space-y-6">
    <div class="text-center">
      <span class="text-4xl block mb-2">🚚</span>
      <h1 class="text-2xl font-black text-slate-900">Track Your Order</h1>
      <p class="text-slate-500 text-xs mt-1">Enter your order number to view real-time delivery status.</p>
    </div>

    <form @submit.prevent="handleTrack" class="space-y-4">
      <div>
        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Order Number</label>
        <input
          v-model="orderNumber"
          type="text"
          required
          placeholder="ORD-2026-XXXXX"
          class="w-full px-4 py-2.5 bg-slate-50 border rounded-xl text-sm font-mono focus:outline-hidden focus:border-indigo-500 uppercase"
        />
      </div>

      <button type="submit" class="w-full py-3 bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-sm rounded-xl shadow-md">
        Track Order Status
      </button>
    </form>
  </div>
</template>

<script setup lang="ts">
const orderNumber = ref('');
function handleTrack() {
  if (orderNumber.value) {
    navigateTo(`/orders/track/${orderNumber.value.trim().toUpperCase()}`);
  }
}
</script>
