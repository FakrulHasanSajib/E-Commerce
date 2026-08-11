<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="bg-slate-900 p-6 rounded-2xl border border-slate-800 flex items-center justify-between">
      <div>
        <h2 class="text-xl font-black text-white">⚡ Flash Sales Manager</h2>
        <p class="text-slate-400 text-xs mt-1">Create timed flash sale campaigns with product slots, quantity limits, and countdown timers.</p>
      </div>
      <button @click="openForm()" class="px-5 py-2.5 bg-amber-500 hover:bg-amber-600 text-slate-900 font-black text-xs rounded-xl shadow-lg transition-all">
        + Create Flash Sale
      </button>
    </div>

    <!-- Flash Sales Table -->
    <div class="bg-slate-900 rounded-2xl border border-slate-800 overflow-x-auto">
      <table class="w-full text-left text-xs border-collapse">
        <thead>
          <tr class="border-b border-slate-800 text-slate-400 font-mono uppercase">
            <th class="p-4">Campaign Name</th>
            <th class="p-4">Start Time</th>
            <th class="p-4">End Time</th>
            <th class="p-4">Products</th>
            <th class="p-4">Status</th>
            <th class="p-4 text-right">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-800 text-slate-200">
          <tr v-for="sale in flashSales" :key="sale.id" class="hover:bg-slate-800/50 transition-colors">
            <td class="p-4 font-bold text-white">{{ sale.name }}</td>
            <td class="p-4 font-mono text-indigo-300 text-[11px]">{{ sale.starts_at }}</td>
            <td class="p-4 font-mono text-rose-300 text-[11px]">{{ sale.ends_at }}</td>
            <td class="p-4">
              <span class="px-2 py-0.5 bg-slate-800 rounded-lg font-mono text-[10px]">{{ sale.products_count || sale.items?.length || 0 }} items</span>
            </td>
            <td class="p-4">
              <span :class="['px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase', isActive(sale) ? 'bg-emerald-500/20 text-emerald-300' : isUpcoming(sale) ? 'bg-amber-500/20 text-amber-300' : 'bg-slate-700 text-slate-400']">
                {{ isActive(sale) ? '🟢 Live' : isUpcoming(sale) ? '🟡 Upcoming' : '⚫ Ended' }}
              </span>
            </td>
            <td class="p-4 text-right space-x-3">
              <button @click="openForm(sale)" class="text-indigo-400 font-bold hover:underline">Edit</button>
              <button @click="deleteSale(sale.id)" class="text-rose-400 font-bold hover:underline">Delete</button>
            </td>
          </tr>
        </tbody>
      </table>

      <div v-if="!flashSales.length && !isLoading" class="text-center py-12 text-slate-500 text-xs space-y-2">
        <div class="text-3xl">⚡</div>
        <div>No flash sale campaigns created yet.</div>
      </div>

      <div v-if="isLoading" class="p-6 space-y-3">
        <div v-for="i in 3" :key="i" class="h-10 bg-slate-800 rounded-xl animate-pulse"></div>
      </div>
    </div>

    <!-- Create / Edit Flash Sale Modal -->
    <div v-if="form.open" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/80 p-4">
      <div class="bg-slate-900 border border-slate-800 rounded-2xl p-6 max-w-lg w-full space-y-5 shadow-2xl">
        <div class="flex items-center justify-between">
          <h3 class="font-bold text-white text-base">{{ form.id ? 'Edit Flash Sale' : 'Create Flash Sale Campaign' }}</h3>
          <button @click="form.open = false" class="text-slate-400 hover:text-white font-bold">✕</button>
        </div>

        <div class="space-y-4">
          <!-- Campaign Name -->
          <div>
            <label class="block text-[10px] font-mono uppercase text-slate-400 mb-1.5">Campaign Name</label>
            <input v-model="form.name" type="text" placeholder="e.g. Eid Mega Flash Sale" required
              class="w-full px-4 py-2.5 bg-slate-950 border border-slate-800 text-white text-xs rounded-xl focus:outline-none focus:border-indigo-500 transition-colors" />
          </div>

          <!-- Start & End Times -->
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-[10px] font-mono uppercase text-slate-400 mb-1.5">Start Time</label>
              <input v-model="form.starts_at" type="datetime-local" required
                class="w-full px-4 py-2.5 bg-slate-950 border border-slate-800 text-white text-xs rounded-xl focus:outline-none focus:border-indigo-500 transition-colors" />
            </div>
            <div>
              <label class="block text-[10px] font-mono uppercase text-slate-400 mb-1.5">End Time</label>
              <input v-model="form.ends_at" type="datetime-local" required
                class="w-full px-4 py-2.5 bg-slate-950 border border-slate-800 text-white text-xs rounded-xl focus:outline-none focus:border-indigo-500 transition-colors" />
            </div>
          </div>

          <!-- Product Items (Flash Sale Slots) -->
          <div>
            <div class="flex items-center justify-between mb-2">
              <label class="text-[10px] font-mono uppercase text-slate-400">Product Slots</label>
              <button type="button" @click="addItem" class="text-[10px] text-indigo-400 font-bold hover:underline">+ Add Product</button>
            </div>
            <div class="space-y-2">
              <div v-for="(item, index) in form.items" :key="index" class="grid grid-cols-3 gap-2 items-center">
                <input v-model.number="item.product_id" type="number" placeholder="Product ID"
                  class="px-3 py-2 bg-slate-950 border border-slate-800 text-white text-[11px] rounded-xl col-span-1" />
                <input v-model.number="item.sale_price" type="number" placeholder="Flash Price ৳" step="0.01"
                  class="px-3 py-2 bg-slate-950 border border-slate-800 text-white text-[11px] rounded-xl col-span-1" />
                <div class="flex gap-1.5 items-center">
                  <input v-model.number="item.quantity_limit" type="number" placeholder="Qty Limit"
                    class="flex-1 px-3 py-2 bg-slate-950 border border-slate-800 text-white text-[11px] rounded-xl" />
                  <button @click="form.items.splice(index, 1)" class="text-rose-400 hover:text-rose-300 font-bold text-base leading-none">✕</button>
                </div>
              </div>
              <div v-if="!form.items.length" class="text-center py-3 text-slate-600 text-[11px] border border-dashed border-slate-800 rounded-xl">
                Click "+ Add Product" to add flash sale slots
              </div>
            </div>
          </div>
        </div>

        <!-- Modal Footer -->
        <div class="flex gap-3 justify-end pt-2 border-t border-slate-800">
          <button @click="form.open = false" class="px-5 py-2.5 text-xs font-bold text-slate-400 hover:text-white transition-colors">
            Cancel
          </button>
          <button @click="saveSale" :disabled="isSaving"
            class="px-5 py-2.5 bg-amber-500 hover:bg-amber-600 text-slate-900 font-black text-xs rounded-xl shadow-lg transition-all disabled:opacity-50">
            {{ isSaving ? 'Saving...' : form.id ? 'Update Campaign' : 'Launch Campaign' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue';
import api from '../services/api';

const flashSales = ref<any[]>([]);
const isLoading = ref(false);
const isSaving = ref(false);

const defaultForm = () => ({
  open: false,
  id: null as number | null,
  name: '',
  starts_at: '',
  ends_at: '',
  items: [] as { product_id: number | null; sale_price: number | null; quantity_limit: number | null }[],
});

const form = ref(defaultForm());

onMounted(() => { fetchFlashSales(); });

async function fetchFlashSales() {
  isLoading.value = true;
  try {
    const res = await api.get('/admin/flash-sales');
    flashSales.value = res.data?.data || [];
  } catch {
    flashSales.value = [];
  } finally {
    isLoading.value = false;
  }
}

function openForm(sale?: any) {
  form.value = {
    open: true,
    id: sale?.id || null,
    name: sale?.name || '',
    starts_at: sale?.starts_at?.slice(0, 16) || '',
    ends_at: sale?.ends_at?.slice(0, 16) || '',
    items: sale?.items?.map((i: any) => ({ product_id: i.product_id, sale_price: i.sale_price, quantity_limit: i.quantity_limit })) || [],
  };
}

function addItem() {
  form.value.items.push({ product_id: null, sale_price: null, quantity_limit: null });
}

async function saveSale() {
  isSaving.value = true;
  try {
    const payload = {
      name: form.value.name,
      starts_at: form.value.starts_at,
      ends_at: form.value.ends_at,
      items: form.value.items,
    };

    if (form.value.id) {
      await api.put(`/admin/flash-sales/${form.value.id}`, payload);
    } else {
      await api.post('/admin/flash-sales', payload);
    }

    form.value.open = false;
    await fetchFlashSales();
  } catch (e: any) {
    alert(e.response?.data?.message || 'Failed to save flash sale.');
  } finally {
    isSaving.value = false;
  }
}

async function deleteSale(id: number) {
  if (confirm('Delete this flash sale campaign? This action cannot be undone.')) {
    try {
      await api.delete(`/admin/flash-sales/${id}`);
      await fetchFlashSales();
    } catch (e: any) {
      alert(e.response?.data?.message || 'Failed to delete flash sale.');
    }
  }
}

function isActive(sale: any): boolean {
  const now = new Date();
  return new Date(sale.starts_at) <= now && new Date(sale.ends_at) >= now;
}

function isUpcoming(sale: any): boolean {
  return new Date(sale.starts_at) > new Date();
}
</script>
