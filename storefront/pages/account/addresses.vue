<template>
  <div class="space-y-6">
    <div class="bg-white p-6 rounded-2xl border border-slate-100 shadow-xs flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-black text-slate-900">Address Book</h1>
        <p class="text-slate-500 text-xs mt-1">Manage saved shipping addresses for fast checkout.</p>
      </div>

      <button @click="showAddModal = true" class="px-4 py-2 bg-indigo-600 text-white text-xs font-bold rounded-xl shadow-xs">
        + Add New Address
      </button>
    </div>

    <!-- Address Cards Grid -->
    <div v-if="pending" class="grid grid-cols-1 md:grid-cols-2 gap-6">
      <div v-for="i in 2" :key="i" class="h-36 bg-white rounded-2xl animate-pulse border"></div>
    </div>

    <div v-else-if="addresses && addresses.length > 0" class="grid grid-cols-1 md:grid-cols-2 gap-6">
      <div v-for="addr in addresses" :key="addr.id" class="bg-white p-6 rounded-2xl border border-slate-100 shadow-xs space-y-3 relative">
        <div class="flex items-center justify-between">
          <span class="font-bold text-sm text-slate-900">{{ addr.name }}</span>
          <span v-if="addr.is_default" class="px-2 py-0.5 bg-emerald-100 text-emerald-700 text-[10px] font-bold rounded-md">Default</span>
        </div>
        <p class="text-xs text-slate-600">{{ addr.street_address }}, {{ addr.city }}, {{ addr.country_code }}</p>
        <div class="text-xs font-mono text-slate-500">📞 {{ addr.phone }}</div>

        <div class="flex gap-3 pt-2">
          <button @click="deleteAddress(addr.id)" class="text-xs text-rose-500 font-bold hover:underline">Delete</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
definePageMeta({ middleware: ['auth'], layout: 'account' });
const { $api } = useNuxtApp();
const showAddModal = ref(false);

const { data: addresses, pending, refresh } = await useAsyncData('customer-addresses', async () => {
  try {
    const res = await $api.get('/customer/addresses');
    return res.data?.data || [];
  } catch (e) {
    return [];
  }
});

async function deleteAddress(id: number) {
  if (confirm('Delete this address?')) {
    await $api.delete(`/customer/addresses/${id}`);
    refresh();
  }
}
</script>

