<template>
  <div class="space-y-6">
    <div class="bg-slate-900 p-6 rounded-2xl border border-slate-800 flex items-center justify-between flex-wrap gap-4">
      <div>
        <h2 class="text-xl font-black text-white">Customer Directory</h2>
        <p class="text-slate-400 text-xs mt-1">View customer profiles, order history, wallet, loyalty, and addresses for support.</p>
      </div>
      <input v-model="search" @input="fetchCustomers" type="text" placeholder="Search by name or email..."
        class="px-4 py-2 bg-slate-950 border border-slate-800 text-white text-xs rounded-xl w-64 focus:outline-none focus:border-indigo-500" />
    </div>

    <!-- Customer Table -->
    <div class="bg-slate-900 rounded-2xl border border-slate-800 overflow-x-auto">
      <table class="w-full text-left text-xs border-collapse">
        <thead>
          <tr class="border-b border-slate-800 text-slate-400 font-mono uppercase">
            <th class="p-4">Customer</th>
            <th class="p-4">Email</th>
            <th class="p-4">Orders</th>
            <th class="p-4">Wallet</th>
            <th class="p-4">Loyalty Pts</th>
            <th class="p-4">Joined</th>
            <th class="p-4 text-right">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-800 text-slate-200">
          <tr v-for="c in customers" :key="c.id" class="hover:bg-slate-800/50 transition-colors">
            <td class="p-4">
              <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-full bg-indigo-600 flex items-center justify-center text-white font-bold text-xs shrink-0">
                  {{ c.name?.charAt(0)?.toUpperCase() }}
                </div>
                <div>
                  <div class="font-bold text-white">{{ c.name }}</div>
                  <div class="text-slate-500 text-[10px]">ID: {{ c.id }}</div>
                </div>
              </div>
            </td>
            <td class="p-4 font-mono text-slate-300">{{ c.email }}</td>
            <td class="p-4 font-bold text-indigo-300">{{ c.orders_count || 0 }}</td>
            <td class="p-4 font-bold text-emerald-300">৳{{ c.wallet_balance || '0.00' }}</td>
            <td class="p-4 font-bold text-amber-300">{{ c.loyalty_points || 0 }}</td>
            <td class="p-4 text-slate-400 font-mono text-[11px]">{{ c.created_at?.slice(0, 10) }}</td>
            <td class="p-4 text-right">
              <button @click="viewCustomer(c)" class="text-indigo-400 font-bold hover:underline">View Profile</button>
            </td>
          </tr>
        </tbody>
      </table>
      <!-- Pagination -->
      <div class="flex items-center justify-between px-4 py-3 border-t border-slate-800 text-xs text-slate-400">
        <span>Showing {{ customers.length }} of {{ meta.total || 0 }} customers</span>
        <div class="flex gap-2">
          <button @click="page > 1 && (page--, fetchCustomers())" :disabled="page <= 1"
            class="px-3 py-1 bg-slate-800 rounded-lg disabled:opacity-40 hover:bg-slate-700">← Prev</button>
          <span class="px-3 py-1">Page {{ page }}</span>
          <button @click="page < meta.last_page && (page++, fetchCustomers())" :disabled="page >= meta.last_page"
            class="px-3 py-1 bg-slate-800 rounded-lg disabled:opacity-40 hover:bg-slate-700">Next →</button>
        </div>
      </div>
    </div>

    <!-- Customer Profile Drawer -->
    <div v-if="selectedCustomer" class="fixed inset-0 z-50 flex justify-end bg-slate-950/70">
      <div class="w-full max-w-lg bg-slate-900 border-l border-slate-800 h-full overflow-y-auto p-6 space-y-6">
        <div class="flex items-center justify-between">
          <h3 class="font-bold text-white text-base">{{ selectedCustomer.name }}</h3>
          <button @click="selectedCustomer = null" class="text-slate-400 hover:text-white font-bold text-xl">✕</button>
        </div>

        <!-- Profile Info -->
        <div class="grid grid-cols-2 gap-3 text-xs">
          <div class="p-3 bg-slate-950 rounded-xl border border-slate-800 space-y-1">
            <div class="text-slate-400">Email</div>
            <div class="font-mono text-white">{{ selectedCustomer.email }}</div>
          </div>
          <div class="p-3 bg-slate-950 rounded-xl border border-slate-800 space-y-1">
            <div class="text-slate-400">Phone</div>
            <div class="font-mono text-white">{{ selectedCustomer.phone || '—' }}</div>
          </div>
          <div class="p-3 bg-emerald-900/30 rounded-xl border border-emerald-800/50 space-y-1">
            <div class="text-slate-400">Wallet Balance</div>
            <div class="font-black text-emerald-300 text-lg">৳{{ selectedCustomer.wallet_balance || '0.00' }}</div>
          </div>
          <div class="p-3 bg-amber-900/30 rounded-xl border border-amber-800/50 space-y-1">
            <div class="text-slate-400">Loyalty Points</div>
            <div class="font-black text-amber-300 text-lg">{{ selectedCustomer.loyalty_points || 0 }} pts</div>
          </div>
        </div>

        <!-- Customer Orders -->
        <div class="space-y-2">
          <h4 class="font-bold text-white text-xs uppercase tracking-widest">Order History</h4>
          <div v-for="o in customerOrders" :key="o.id"
            class="flex justify-between p-3 bg-slate-950 rounded-xl border border-slate-800 text-xs">
            <div>
              <div class="font-mono text-indigo-400 font-bold">{{ o.order_number }}</div>
              <div class="text-slate-400 text-[10px]">{{ o.created_at?.slice(0, 10) }}</div>
            </div>
            <div class="text-right">
              <div class="font-bold text-white">৳{{ o.grand_total }}</div>
              <span class="text-[10px] font-bold uppercase text-amber-400">{{ o.status }}</span>
            </div>
          </div>
          <div v-if="!customerOrders.length" class="text-center py-4 text-slate-500 text-xs">No orders found.</div>
        </div>

        <!-- Customer Addresses -->
        <div class="space-y-2">
          <h4 class="font-bold text-white text-xs uppercase tracking-widest">Saved Addresses</h4>
          <div v-for="a in customerAddresses" :key="a.id"
            class="p-3 bg-slate-950 rounded-xl border border-slate-800 text-xs text-slate-300">
            {{ a.address_line1 }}{{ a.address_line2 ? ', ' + a.address_line2 : '' }}, {{ a.city }}
          </div>
          <div v-if="!customerAddresses.length" class="text-center py-4 text-slate-500 text-xs">No addresses.</div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue';
import api from '../services/api';

const customers = ref<any[]>([]);
const selectedCustomer = ref<any>(null);
const customerOrders = ref<any[]>([]);
const customerAddresses = ref<any[]>([]);
const search = ref('');
const page = ref(1);
const meta = ref<any>({ total: 0, last_page: 1 });
let searchTimeout: any = null;

onMounted(() => { fetchCustomers(); });

async function fetchCustomers() {
  clearTimeout(searchTimeout);
  searchTimeout = setTimeout(async () => {
    try {
      const res = await api.get('/admin/customers', { params: { page: page.value, search: search.value } });
      customers.value = res.data?.data || [];
      meta.value = res.data?.meta || { total: 0, last_page: 1 };
    } catch { customers.value = []; }
  }, 300);
}

async function viewCustomer(c: any) {
  selectedCustomer.value = c;
  customerOrders.value = [];
  customerAddresses.value = [];
  try {
    const [ordersRes, addrsRes] = await Promise.all([
      api.get(`/admin/customers/${c.id}/orders`),
      api.get(`/admin/customers/${c.id}/addresses`),
    ]);
    customerOrders.value = ordersRes.data?.data || [];
    customerAddresses.value = addrsRes.data?.data || [];
  } catch {}
}
</script>
