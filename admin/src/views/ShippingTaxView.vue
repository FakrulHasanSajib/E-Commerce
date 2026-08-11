<template>
  <div class="space-y-6">
    <div class="bg-slate-900 p-6 rounded-2xl border border-slate-800 flex items-center justify-between">
      <div>
        <h2 class="text-xl font-black text-white">Shipping & Tax Configuration</h2>
        <p class="text-slate-400 text-xs mt-1">Configure shipping zones, rates, tax classes, and pickup point locations.</p>
      </div>
      <div class="flex gap-2">
        <button v-for="tab in tabs" :key="tab.key" @click="activeTab = tab.key"
          :class="['px-3 py-2 rounded-xl text-xs font-bold uppercase transition-all', activeTab === tab.key ? 'bg-indigo-600 text-white' : 'bg-slate-950 text-slate-400 hover:text-white']">
          {{ tab.label }}
        </button>
      </div>
    </div>

    <!-- TAB 1: SHIPPING ZONES -->
    <div v-if="activeTab === 'zones'" class="bg-slate-900 p-6 rounded-2xl border border-slate-800 space-y-4">
      <div class="flex items-center justify-between border-b border-slate-800 pb-3">
        <h3 class="font-bold text-white text-sm">Shipping Zone Rates</h3>
        <button @click="createShippingZone" class="px-3 py-1.5 bg-indigo-600 text-white font-bold text-[10px] rounded-lg">+ Add Zone</button>
      </div>
      <div class="space-y-2">
        <div v-for="zone in shippingZones" :key="zone.id" class="flex items-center justify-between p-3 bg-slate-950 rounded-xl border border-slate-800 text-xs">
          <div>
            <div class="font-bold text-white">{{ zone.name }}</div>
            <div class="text-slate-400">Base Rate: ৳{{ zone.base_rate }} • Free threshold: ৳{{ zone.free_shipping_threshold || '—' }}</div>
          </div>
          <button @click="deleteZone(zone.id)" class="text-rose-400 font-bold hover:underline">Delete</button>
        </div>
      </div>
    </div>

    <!-- TAB 2: TAX CLASSES -->
    <div v-if="activeTab === 'tax'" class="bg-slate-900 p-6 rounded-2xl border border-slate-800 space-y-4">
      <div class="flex items-center justify-between border-b border-slate-800 pb-3">
        <h3 class="font-bold text-white text-sm">Tax Class Configuration</h3>
        <button @click="createTaxClass" class="px-3 py-1.5 bg-indigo-600 text-white font-bold text-[10px] rounded-lg">+ Add Tax Class</button>
      </div>
      <div class="space-y-2">
        <div v-for="tax in taxClasses" :key="tax.id" class="flex items-center justify-between p-3 bg-slate-950 rounded-xl border border-slate-800 text-xs">
          <div>
            <div class="font-bold text-white">{{ tax.name }}</div>
            <div class="text-slate-400">Rate: <span class="font-mono font-bold text-amber-400">{{ tax.rate }}%</span></div>
          </div>
          <button @click="deleteTaxClass(tax.id)" class="text-rose-400 font-bold hover:underline">Delete</button>
        </div>
      </div>
    </div>

    <!-- TAB 3: PICKUP POINTS -->
    <div v-if="activeTab === 'pickup'" class="bg-slate-900 p-6 rounded-2xl border border-slate-800 space-y-4">
      <div class="flex items-center justify-between border-b border-slate-800 pb-3">
        <h3 class="font-bold text-white text-sm">Courier Pickup Point Locations</h3>
        <button @click="createPickupPoint" class="px-3 py-1.5 bg-indigo-600 text-white font-bold text-[10px] rounded-lg">+ Add Pickup Point</button>
      </div>
      <div class="space-y-2">
        <div v-for="pp in pickupPoints" :key="pp.id" class="flex items-center justify-between p-3 bg-slate-950 rounded-xl border border-slate-800 text-xs">
          <div>
            <div class="font-bold text-white">{{ pp.name }}</div>
            <div class="text-slate-400">{{ pp.address }} • Provider: <span class="font-mono text-indigo-400">{{ pp.courier_provider }}</span></div>
          </div>
          <button @click="deletePickupPoint(pp.id)" class="text-rose-400 font-bold hover:underline">Delete</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue';
import api from '../services/api';

const tabs = [
  { key: 'zones', label: 'Shipping Zones' },
  { key: 'tax', label: 'Tax Classes' },
  { key: 'pickup', label: 'Pickup Points' },
];
const activeTab = ref('zones');
const shippingZones = ref<any[]>([]);
const taxClasses = ref<any[]>([]);
const pickupPoints = ref<any[]>([]);

onMounted(() => { fetchAll(); });

async function fetchAll() {
  try { const r = await api.get('/admin/shipping-zones'); shippingZones.value = r.data?.data || []; } catch {}
  try { const r = await api.get('/admin/tax-classes'); taxClasses.value = r.data?.data || []; } catch {}
  try { const r = await api.get('/admin/pickup-points'); pickupPoints.value = r.data?.data || []; } catch {}
}
async function createShippingZone() {
  const name = prompt('Zone name (e.g. Dhaka City):');
  if (name) { await api.post('/admin/shipping-zones', { name, base_rate: 60 }); fetchAll(); }
}
async function deleteZone(id: number) {
  if (confirm('Delete shipping zone?')) { await api.delete(`/admin/shipping-zones/${id}`); fetchAll(); }
}
async function createTaxClass() {
  const name = prompt('Tax class name:');
  const rate = prompt('Tax rate (%):');
  if (name && rate) { await api.post('/admin/tax-classes', { name, rate: Number(rate) }); fetchAll(); }
}
async function deleteTaxClass(id: number) {
  if (confirm('Delete tax class?')) { await api.delete(`/admin/tax-classes/${id}`); fetchAll(); }
}
async function createPickupPoint() {
  const name = prompt('Pickup point name:');
  if (name) { await api.post('/admin/pickup-points', { name, address: 'Dhaka', courier_provider: 'pathao' }); fetchAll(); }
}
async function deletePickupPoint(id: number) {
  if (confirm('Delete pickup point?')) { await api.delete(`/admin/pickup-points/${id}`); fetchAll(); }
}
</script>
