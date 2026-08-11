<template>
  <div class="space-y-6">
    <div class="bg-white p-6 rounded-2xl border border-slate-100 shadow-xs flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-black text-slate-900">Return & Refund Requests</h1>
        <p class="text-slate-500 text-xs mt-1">Submit return requests for delivered orders and track QC approval.</p>
      </div>

      <button @click="showModal = true" class="px-4 py-2 bg-indigo-600 text-white text-xs font-bold rounded-xl shadow-xs">
        + Request Return
      </button>
    </div>

    <!-- Return Requests List -->
    <div v-if="pending" class="space-y-4">
      <div v-for="i in 2" :key="i" class="h-24 bg-white rounded-2xl animate-pulse border"></div>
    </div>

    <div v-else-if="returns && returns.length > 0" class="space-y-4">
      <div v-for="ret in returns" :key="ret.id" class="bg-white p-6 rounded-2xl border border-slate-100 shadow-xs flex items-center justify-between gap-4">
        <div>
          <div class="font-bold text-slate-900 text-sm">Return Request #{{ ret.id }} (Order #{{ ret.order_id }})</div>
          <div class="text-xs text-slate-500 mt-0.5">Reason: {{ ret.reason }} • Method: <span class="uppercase font-bold text-indigo-600">{{ ret.refund_method }}</span></div>
        </div>

        <div class="text-right">
          <div class="font-black text-slate-900 text-sm">৳{{ ret.refund_amount }}</div>
          <span :class="['px-2.5 py-0.5 rounded-full text-[11px] font-bold uppercase mt-1 inline-block', ret.status === 'approved' || ret.status === 'refunded' ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700']">
            {{ ret.status }}
          </span>
        </div>
      </div>
    </div>

    <!-- Create Return Request Modal -->
    <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 p-4">
      <div class="bg-white p-6 rounded-2xl max-w-md w-full space-y-4">
        <h3 class="font-bold text-slate-900 text-base">Submit Return Request</h3>

        <div>
          <label class="block text-xs font-bold text-slate-700 mb-1">Order ID</label>
          <input v-model.number="form.order_id" type="number" placeholder="Enter Order ID" required class="w-full px-3 py-2 bg-slate-50 border rounded-xl text-xs" />
        </div>

        <div>
          <label class="block text-xs font-bold text-slate-700 mb-1">Reason for Return</label>
          <textarea v-model="form.reason" placeholder="Damaged or incorrect size..." required class="w-full px-3 py-2 bg-slate-50 border rounded-xl text-xs"></textarea>
        </div>

        <div>
          <label class="block text-xs font-bold text-slate-700 mb-1">Refund Method Choice</label>
          <select v-model="form.refund_method" class="w-full px-3 py-2 bg-slate-50 border rounded-xl text-xs font-bold">
            <option value="wallet">Instant Wallet Credit</option>
            <option value="bank">Manual Bank Transfer</option>
          </select>
        </div>

        <div class="flex gap-2 justify-end pt-2">
          <button @click="showModal = false" class="px-4 py-2 text-xs font-bold text-slate-500">Cancel</button>
          <button @click="submitReturn" class="px-4 py-2 bg-indigo-600 text-white text-xs font-bold rounded-xl">Submit Request</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
definePageMeta({ middleware: ['auth'], layout: 'account' });
const { $api } = useNuxtApp();
const showModal = ref(false);

const form = reactive({
  order_id: null as number | null,
  reason: '',
  refund_method: 'wallet',
  items: [{ order_item_id: 1, quantity: 1 }],
});

const { data: returns, pending, refresh } = await useAsyncData('customer-returns-list', async () => {
  try {
    const res = await $api.get('/customer/returns');
    return res.data?.data || [];
  } catch (e) {
    return [];
  }
});

async function submitReturn() {
  try {
    await $api.post('/customer/returns', form);
    alert('Return request submitted successfully!');
    showModal.value = false;
    refresh();
  } catch (e: any) {
    alert(e.response?.data?.message || 'Failed to submit return request.');
  }
}
</script>

