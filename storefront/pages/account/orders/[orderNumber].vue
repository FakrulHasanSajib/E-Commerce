<template>
  <div class="max-w-4xl mx-auto space-y-6">
    <div class="bg-white p-6 rounded-2xl border border-slate-100 shadow-xs flex items-center justify-between">
      <div>
        <div class="text-xs text-slate-400">
          <NuxtLink to="/account/orders" class="hover:text-indigo-600">My Orders</NuxtLink> / Details
        </div>
        <h1 class="text-2xl font-black text-slate-900">Order #{{ route.params.orderNumber }}</h1>
      </div>

      <button @click="downloadInvoice" class="px-4 py-2 bg-indigo-600 text-white text-xs font-bold rounded-xl shadow-xs hover:bg-indigo-700">
        📄 Download Invoice
      </button>
    </div>

    <!-- Order Items & Summary -->
    <div v-if="order" class="bg-white p-8 rounded-3xl border border-slate-100 shadow-xs space-y-6">
      <div class="flex items-center justify-between border-b border-slate-100 pb-4 text-xs">
        <div>
          <span class="text-slate-400">Date:</span> <strong>{{ order.created_at }}</strong>
        </div>
        <div>
          <span class="text-slate-400">Payment Status:</span>
          <span :class="['ml-1 font-bold uppercase', order.payment_status === 'paid' ? 'text-emerald-600' : 'text-amber-600']">
            {{ order.payment_status }}
          </span>
        </div>
        <div>
          <span class="text-slate-400">Status:</span>
          <span class="ml-1 font-bold uppercase text-indigo-600">{{ order.status }}</span>
        </div>
      </div>

      <!-- Line Items -->
      <div class="space-y-4">
        <h3 class="font-bold text-slate-900 text-xs uppercase tracking-wider">Ordered Items</h3>
        <div v-for="item in order.items" :key="item.id" class="flex items-center justify-between p-3 bg-slate-50 rounded-xl border border-slate-100 text-xs">
          <div>
            <div class="font-bold text-slate-900">{{ item.name }}</div>
            <div class="text-slate-500">Qty: {{ item.quantity }} x ৳{{ item.price }}</div>
          </div>
          <div class="font-black text-slate-900">৳{{ item.price * item.quantity }}</div>
        </div>
      </div>

      <!-- Totals -->
      <div class="border-t border-slate-100 pt-4 text-xs space-y-1 text-right">
        <div>Subtotal: <strong>৳{{ order.subtotal }}</strong></div>
        <div>Discount: <strong>-৳{{ order.discount }}</strong></div>
        <div class="text-base font-black text-slate-900 pt-2">Grand Total: ৳{{ order.grand_total }}</div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { useRoute } from 'vue-router';
definePageMeta({ middleware: ['auth'] });

const route = useRoute();
const { $api } = useNuxtApp();

const { data: order } = await useAsyncData(`order-detail-${route.params.orderNumber}`, async () => {
  try {
    const res = await $api.get(`/orders/track/${route.params.orderNumber}`);
    return res.data?.order || res.data?.data;
  } catch (e) {
    return null;
  }
});

function downloadInvoice() {
  alert(`Downloading invoice for Order #${route.params.orderNumber}...`);
}
</script>
