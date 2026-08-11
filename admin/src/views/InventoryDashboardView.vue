<template>
  <div class="space-y-6">
    <div class="bg-slate-900 p-6 rounded-2xl border border-slate-800 flex items-center justify-between">
      <div>
        <h2 class="text-xl font-black text-white">Inventory Dashboard</h2>
        <p class="text-slate-400 text-xs mt-1">Monitor stock levels, adjust quantities, and manage warehouse inventory.</p>
      </div>
    </div>

    <!-- KPI Row -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
      <div class="bg-slate-900 p-5 rounded-2xl border border-slate-800 space-y-1">
        <div class="text-[10px] font-mono uppercase text-slate-400">Total SKUs</div>
        <div class="text-2xl font-black text-white">{{ stats.total_skus || 0 }}</div>
      </div>
      <div class="bg-rose-900/30 p-5 rounded-2xl border border-rose-800/50 space-y-1">
        <div class="text-[10px] font-mono uppercase text-rose-400">Out of Stock</div>
        <div class="text-2xl font-black text-rose-300">{{ stats.out_of_stock || 0 }}</div>
      </div>
      <div class="bg-amber-900/30 p-5 rounded-2xl border border-amber-800/50 space-y-1">
        <div class="text-[10px] font-mono uppercase text-amber-400">Low Stock (≤10)</div>
        <div class="text-2xl font-black text-amber-300">{{ stats.low_stock || 0 }}</div>
      </div>
      <div class="bg-emerald-900/30 p-5 rounded-2xl border border-emerald-800/50 space-y-1">
        <div class="text-[10px] font-mono uppercase text-emerald-400">Healthy Stock</div>
        <div class="text-2xl font-black text-emerald-300">{{ stats.healthy_stock || 0 }}</div>
      </div>
    </div>

    <!-- Low Stock Alert Table -->
    <div class="bg-slate-900 rounded-2xl border border-slate-800 overflow-x-auto">
      <div class="p-4 border-b border-slate-800 flex items-center justify-between">
        <h3 class="font-bold text-amber-400 text-sm">⚠ Low Stock Products</h3>
        <input v-model="search" @input="fetchLowStock" placeholder="Search products..."
          class="px-3 py-1.5 bg-slate-950 border border-slate-800 text-white text-xs rounded-xl w-52 focus:outline-none focus:border-indigo-500" />
      </div>
      <table class="w-full text-left text-xs border-collapse">
        <thead>
          <tr class="border-b border-slate-800 text-slate-400 font-mono uppercase">
            <th class="p-4">Product / SKU</th>
            <th class="p-4">Current Stock</th>
            <th class="p-4">Threshold</th>
            <th class="p-4 text-right">Adjust Stock</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-800 text-slate-200">
          <tr v-for="p in lowStockProducts" :key="p.id" class="hover:bg-slate-800/50">
            <td class="p-4">
              <div class="font-bold text-white">{{ p.name }}</div>
              <div class="text-slate-500 font-mono text-[10px]">SKU: {{ p.sku }}</div>
            </td>
            <td class="p-4">
              <span :class="['font-black text-sm', p.stock_quantity === 0 ? 'text-rose-400' : 'text-amber-400']">
                {{ p.stock_quantity }}
              </span>
            </td>
            <td class="p-4 text-slate-400">{{ p.low_stock_threshold || 10 }}</td>
            <td class="p-4 text-right">
              <div class="flex items-center justify-end gap-2">
                <input v-model.number="adjustments[p.id]" type="number" placeholder="±qty"
                  class="w-20 px-2 py-1 bg-slate-950 border border-slate-700 text-white text-xs rounded-lg text-center" />
                <button @click="adjustStock(p.id)" class="px-3 py-1 bg-indigo-600 text-white font-bold text-[10px] rounded-lg hover:bg-indigo-700">
                  Apply
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
      <div v-if="!lowStockProducts.length" class="text-center py-12 text-emerald-400 text-xs font-bold">
        ✓ All products are well-stocked!
      </div>
    </div>

    <!-- Stock Adjustment Log -->
    <div class="bg-slate-900 p-6 rounded-2xl border border-slate-800 space-y-4">
      <h3 class="font-bold text-white text-sm border-b border-slate-800 pb-3">Recent Stock Adjustments</h3>
      <div class="space-y-2">
        <div v-for="log in adjustmentLogs" :key="log.id"
          class="flex items-center justify-between p-3 bg-slate-950 rounded-xl border border-slate-800 text-xs">
          <div>
            <div class="font-bold text-white">{{ log.product?.name }}</div>
            <div class="text-slate-400">{{ log.reason || 'Manual adjustment' }} • by {{ log.user?.name }}</div>
          </div>
          <div class="text-right">
            <span :class="['font-black text-sm', log.quantity_change > 0 ? 'text-emerald-400' : 'text-rose-400']">
              {{ log.quantity_change > 0 ? '+' : '' }}{{ log.quantity_change }}
            </span>
            <div class="text-slate-500 font-mono text-[10px]">{{ log.created_at?.slice(0, 16) }}</div>
          </div>
        </div>
        <div v-if="!adjustmentLogs.length" class="text-center py-4 text-slate-500 text-xs">No adjustment logs yet.</div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted, reactive } from 'vue';
import api from '../services/api';

const stats = ref<any>({});
const lowStockProducts = ref<any[]>([]);
const adjustmentLogs = ref<any[]>([]);
const adjustments = reactive<Record<number, number>>({});
const search = ref('');
let searchTimeout: any = null;

onMounted(() => { fetchAll(); });

async function fetchAll() {
  try { const r = await api.get('/admin/dashboard/low-stock'); lowStockProducts.value = r.data?.data || []; } catch {}
  try { const r = await api.get('/admin/inventory/stats'); stats.value = r.data || {}; } catch {}
  try { const r = await api.get('/admin/inventory/adjustment-logs'); adjustmentLogs.value = r.data?.data || []; } catch {}
}

function fetchLowStock() {
  clearTimeout(searchTimeout);
  searchTimeout = setTimeout(async () => {
    try {
      const r = await api.get('/admin/products', { params: { stock_status: 'low', search: search.value } });
      lowStockProducts.value = r.data?.data || [];
    } catch {}
  }, 300);
}

async function adjustStock(productId: number) {
  const qty = adjustments[productId];
  if (!qty || qty === 0) return alert('Enter a non-zero quantity to adjust.');
  try {
    await api.post(`/admin/products/${productId}/stock-adjustment`, {
      quantity_change: qty,
      reason: 'Manual admin adjustment',
    });
    delete adjustments[productId];
    await fetchAll();
    alert('Stock adjusted successfully!');
  } catch (e: any) {
    alert(e.response?.data?.message || 'Failed to adjust stock.');
  }
}
</script>
