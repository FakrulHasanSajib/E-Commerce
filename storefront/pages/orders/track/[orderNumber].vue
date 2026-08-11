<template>
  <div class="max-w-3xl mx-auto space-y-6">
    <div class="bg-white p-6 rounded-2xl border border-slate-100 shadow-xs">
      <h1 class="text-2xl font-black text-slate-900">Order Tracking Timeline</h1>
      <p class="text-slate-500 text-xs mt-1">Live tracking status for Order #{{ route.params.orderNumber }}</p>
    </div>

    <!-- Timeline Steps -->
    <div v-if="order" class="bg-white p-8 rounded-3xl border border-slate-100 shadow-xs space-y-8">
      <div class="flex items-center justify-between border-b border-slate-100 pb-4">
        <div>
          <div class="text-xs text-slate-400">Order Status:</div>
          <div class="font-black text-slate-900 uppercase text-base">{{ order.status }}</div>
        </div>
        <div v-if="order.courier_tracking_code" class="text-right">
          <div class="text-xs text-slate-400">Courier Tracking Code:</div>
          <div class="font-mono text-xs font-bold text-indigo-600">{{ order.courier_tracking_code }}</div>
        </div>
      </div>

      <!-- Vertical Timeline -->
      <div class="space-y-6 relative pl-6 border-l-2 border-slate-100">
        <div v-for="(log, idx) in order.timeline || defaultTimeline" :key="idx" class="relative">
          <span class="absolute -left-[31px] top-0.5 w-4 h-4 rounded-full bg-indigo-600 border-4 border-white shadow-xs"></span>
          <h4 class="font-bold text-slate-900 text-sm">{{ log.title || log.status }}</h4>
          <p class="text-xs text-slate-500 mt-0.5">{{ log.note || log.description }}</p>
          <span class="text-[10px] text-slate-400 font-mono mt-1 block">{{ log.created_at || 'Just now' }}</span>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { useRoute } from 'vue-router';
const route = useRoute();
const { $api } = useNuxtApp();

const defaultTimeline = [
  { status: 'Order Placed', note: 'Customer successfully placed order.', created_at: '2026-08-09 10:00 AM' },
  { status: 'Confirmed', note: 'Payment verified and order confirmed.', created_at: '2026-08-09 10:15 AM' },
];

const { data: order } = await useAsyncData(`track-order-${route.params.orderNumber}`, async () => {
  try {
    const res = await $api.get(`/orders/track/${route.params.orderNumber}`);
    return res.data?.order || res.data?.data;
  } catch (e) {
    return { status: 'pending', timeline: defaultTimeline };
  }
});
</script>
