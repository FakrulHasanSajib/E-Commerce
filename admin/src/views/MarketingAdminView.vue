<template>
  <div class="space-y-6">
    <div class="bg-slate-900 p-6 rounded-2xl border border-slate-800 flex items-center justify-between">
      <div>
        <h2 class="text-xl font-black text-white">Marketing Control Center</h2>
        <p class="text-slate-400 text-xs mt-1">Manage coupons, gift cards, affiliates, popups, and customer segments.</p>
      </div>
      <div class="flex gap-2">
        <button v-for="tab in tabs" :key="tab.key" @click="activeTab = tab.key"
          :class="['px-3 py-2 rounded-xl text-xs font-bold uppercase transition-all', activeTab === tab.key ? 'bg-indigo-600 text-white' : 'bg-slate-950 text-slate-400 hover:text-white']">
          {{ tab.label }}
        </button>
      </div>
    </div>

    <!-- TAB 1: COUPONS & GIFT CARDS -->
    <div v-if="activeTab === 'coupons'" class="grid grid-cols-1 md:grid-cols-2 gap-8">
      <!-- Coupons -->
      <div class="bg-slate-900 p-6 rounded-2xl border border-slate-800 space-y-4">
        <div class="flex items-center justify-between border-b border-slate-800 pb-3">
          <h3 class="font-bold text-white text-sm">Discount Coupons</h3>
          <button @click="createCoupon" class="px-3 py-1.5 bg-indigo-600 text-white font-bold text-[10px] rounded-lg">+ Add Coupon</button>
        </div>
        <div class="space-y-2">
          <div v-for="c in coupons" :key="c.id" class="flex items-center justify-between p-3 bg-slate-950 rounded-xl border border-slate-800 text-xs">
            <div>
              <div class="font-mono font-bold text-indigo-400">{{ c.code }}</div>
              <div class="text-slate-400">{{ c.type }} — {{ c.value }}{{ c.type === 'percent' ? '%' : '৳' }}</div>
            </div>
            <button @click="deleteCoupon(c.id)" class="text-rose-400 font-bold hover:underline">Delete</button>
          </div>
        </div>
      </div>

      <!-- Gift Cards -->
      <div class="bg-slate-900 p-6 rounded-2xl border border-slate-800 space-y-4">
        <div class="flex items-center justify-between border-b border-slate-800 pb-3">
          <h3 class="font-bold text-white text-sm">Gift Cards</h3>
          <button @click="createGiftCard" class="px-3 py-1.5 bg-emerald-600 text-white font-bold text-[10px] rounded-lg">+ Issue Gift Card</button>
        </div>
        <div class="space-y-2">
          <div v-for="g in giftCards" :key="g.id" class="flex items-center justify-between p-3 bg-slate-950 rounded-xl border border-slate-800 text-xs">
            <div>
              <div class="font-mono font-bold text-emerald-400">{{ g.code }}</div>
              <div class="text-slate-400">Balance: ৳{{ g.balance }} / ৳{{ g.initial_amount }}</div>
            </div>
            <span :class="['px-2 py-0.5 rounded text-[10px] font-bold uppercase', g.is_active ? 'bg-emerald-500/20 text-emerald-300' : 'bg-rose-500/20 text-rose-300']">
              {{ g.is_active ? 'Active' : 'Expired' }}
            </span>
          </div>
        </div>
      </div>
    </div>

    <!-- TAB 2: AFFILIATES -->
    <div v-if="activeTab === 'affiliates'" class="bg-slate-900 p-6 rounded-2xl border border-slate-800 space-y-4">
      <h3 class="font-bold text-white text-sm border-b border-slate-800 pb-3">Affiliate Partner Directory</h3>
      <div class="overflow-x-auto">
        <table class="w-full text-xs border-collapse text-left">
          <thead><tr class="border-b border-slate-800 text-slate-400 font-mono uppercase">
            <th class="p-4">Partner Name</th><th class="p-4">Code</th><th class="p-4">Total Earnings</th><th class="p-4">Status</th>
          </tr></thead>
          <tbody class="divide-y divide-slate-800 text-slate-200">
            <tr v-for="a in affiliates" :key="a.id" class="hover:bg-slate-800/50">
              <td class="p-4 font-bold text-white">{{ a.user?.name }}</td>
              <td class="p-4 font-mono text-indigo-400">{{ a.referral_code }}</td>
              <td class="p-4 font-bold">৳{{ a.total_earnings }}</td>
              <td class="p-4">
                <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-emerald-500/20 text-emerald-300">{{ a.status }}</span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- TAB 3: POPUPS MANAGER -->
    <div v-if="activeTab === 'popups'" class="bg-slate-900 p-6 rounded-2xl border border-slate-800 space-y-4">
      <div class="flex items-center justify-between border-b border-slate-800 pb-3">
        <h3 class="font-bold text-white text-sm">Popup Builder Manager</h3>
        <button @click="createPopup" class="px-3 py-1.5 bg-indigo-600 text-white font-bold text-[10px] rounded-lg">+ Create Popup</button>
      </div>
      <div class="space-y-2">
        <div v-for="p in popups" :key="p.id" class="flex items-center justify-between p-3 bg-slate-950 rounded-xl border border-slate-800 text-xs">
          <div>
            <div class="font-bold text-white">{{ p.name }}</div>
            <div class="text-slate-400">Type: <span class="font-mono text-indigo-400">{{ p.type }}</span> • Delay: {{ p.delay_seconds }}s</div>
          </div>
        </div>
      </div>
    </div>

    <!-- TAB 4: CUSTOMER SEGMENTS -->
    <div v-if="activeTab === 'segments'" class="bg-slate-900 p-6 rounded-2xl border border-slate-800 space-y-4">
      <div class="flex items-center justify-between border-b border-slate-800 pb-3">
        <h3 class="font-bold text-white text-sm">Customer Segments</h3>
        <button @click="recalculateSegments" class="px-3 py-1.5 bg-purple-600 text-white font-bold text-[10px] rounded-lg">↻ Recalculate All Segments</button>
      </div>
      <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div v-for="seg in segments" :key="seg.id" class="p-4 bg-slate-950 rounded-xl border border-slate-800 text-xs space-y-1">
          <div class="font-bold text-white">{{ seg.name }}</div>
          <div class="text-slate-400">{{ seg.description }}</div>
          <div class="font-mono text-indigo-400 text-[10px]">{{ seg.customer_count }} customers</div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue';
import api from '../services/api';

const tabs = [
  { key: 'coupons', label: 'Coupons & Gift Cards' },
  { key: 'affiliates', label: 'Affiliates' },
  { key: 'popups', label: 'Popups' },
  { key: 'segments', label: 'Segments' },
];
const activeTab = ref('coupons');
const coupons = ref<any[]>([]);
const giftCards = ref<any[]>([]);
const affiliates = ref<any[]>([]);
const popups = ref<any[]>([]);
const segments = ref<any[]>([]);

onMounted(() => { fetchAll(); });

async function fetchAll() {
  try { const r = await api.get('/admin/coupons'); coupons.value = r.data?.data || []; } catch {}
  try { const r = await api.get('/admin/gift-cards'); giftCards.value = r.data?.data || []; } catch {}
  try { const r = await api.get('/admin/affiliates'); affiliates.value = r.data?.data || []; } catch {}
  try { const r = await api.get('/admin/popups'); popups.value = r.data?.data || []; } catch {}
  try { const r = await api.get('/admin/customer-segments'); segments.value = r.data?.data || []; } catch {}
}

async function createCoupon() {
  const code = prompt('Coupon code:');
  if (code) { await api.post('/admin/coupons', { code, type: 'percent', value: 10, min_order_amount: 0 }); fetchAll(); }
}
async function deleteCoupon(id: number) {
  if (confirm('Delete coupon?')) { await api.delete(`/admin/coupons/${id}`); fetchAll(); }
}
async function createGiftCard() {
  const amount = prompt('Gift card amount (৳):');
  if (amount) { await api.post('/admin/gift-cards', { initial_amount: Number(amount) }); fetchAll(); }
}
async function createPopup() {
  const name = prompt('Popup name:');
  if (name) { await api.post('/admin/popups', { name, type: 'newsletter', delay_seconds: 5, title: name, content: '' }); fetchAll(); }
}
async function recalculateSegments() {
  await api.post('/admin/customer-segments/recalculate');
  alert('Segment recalculation triggered!');
  fetchAll();
}
</script>
