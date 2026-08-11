<template>
  <div class="space-y-6">
    <div class="bg-white p-6 rounded-2xl border border-slate-100 shadow-xs">
      <h1 class="text-2xl font-black text-slate-900">My Orders</h1>
      <p class="text-slate-500 text-xs mt-1">Track your order history, delivery status, and download invoices.</p>
    </div>

    <!-- Order Cards List -->
    <div v-if="pending" class="space-y-4">
      <div v-for="i in 3" :key="i" class="h-24 bg-white rounded-2xl animate-pulse border"></div>
    </div>

    <div v-else-if="orders && orders.length > 0" class="space-y-4">
      <div v-for="order in orders" :key="order.id" class="bg-white p-6 rounded-2xl border border-slate-100 shadow-xs flex items-center justify-between gap-4 flex-wrap">
        <div>
          <div class="font-bold text-slate-900 text-sm">Order #{{ order.order_number }}</div>
          <div class="text-xs text-slate-500 mt-0.5">Placed on: {{ order.created_at }}</div>
          <div class="text-xs text-slate-400 mt-0.5">{{ order.items_count || '—' }} item(s)</div>
        </div>

        <div class="text-right">
          <div class="font-black text-slate-900 text-sm">৳{{ order.grand_total }}</div>
          <span :class="['px-2.5 py-0.5 rounded-full text-[11px] font-bold uppercase mt-1 inline-block',
            order.status === 'delivered' ? 'bg-emerald-100 text-emerald-700' :
            order.status === 'cancelled' ? 'bg-rose-100 text-rose-700' :
            order.status === 'shipped' ? 'bg-blue-100 text-blue-700' :
            'bg-indigo-100 text-indigo-700']">
            {{ order.status }}
          </span>
        </div>

        <div class="flex gap-2">
          <NuxtLink :to="`/account/orders/${order.order_number}`" class="px-4 py-2 bg-indigo-600 text-white font-bold text-xs rounded-xl hover:bg-indigo-700 transition-all">
            View Details
          </NuxtLink>
          <NuxtLink :to="`/orders/track/${order.order_number}`" class="px-4 py-2 bg-slate-900 text-white font-bold text-xs rounded-xl hover:bg-slate-800 transition-all">
            Track
          </NuxtLink>
        </div>
      </div>
    </div>

    <div v-else class="bg-white p-12 rounded-2xl border text-center space-y-4">
      <div class="text-4xl">📦</div>
      <div class="font-bold text-slate-900">No orders placed yet.</div>
      <NuxtLink to="/products" class="inline-block px-6 py-2 bg-indigo-600 text-white font-bold text-xs rounded-xl">Start Shopping</NuxtLink>
    </div>
  </div>
</template>

<script setup lang="ts">
definePageMeta({ middleware: ['auth'], layout: 'account' });
const { $api } = useNuxtApp();

const { data: orders, pending } = await useAsyncData('customer-orders-list', async () => {
  try {
    const res = await $api.get('/customer/orders');
    return res.data?.data || [];
  } catch {
    return [];
  }
});
</script>

