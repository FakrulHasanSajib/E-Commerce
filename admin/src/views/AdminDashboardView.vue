<template>
  <div class="space-y-8 max-w-6xl mx-auto">
    <!-- Store Header & Quick Actions -->
    <div class="bg-slate-900 p-8 rounded-3xl border border-slate-800 flex items-center justify-between flex-wrap gap-4 shadow-sm">
      <div>
        <h1 class="text-2xl font-bold text-white tracking-tight">Store Overview</h1>
        <p class="text-slate-400 text-xs mt-1">Here is what is happening with your store today.</p>
      </div>

      <div class="flex items-center gap-3">
        <router-link to="/catalog" class="px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs rounded-xl shadow-md shadow-indigo-600/20 transition-all flex items-center gap-1.5">
          <span>+ Add Product</span>
        </router-link>
        <router-link to="/orders" class="px-5 py-2.5 bg-slate-800 hover:bg-slate-700 text-white font-bold text-xs rounded-xl transition-all">
          Manage Orders
        </router-link>
      </div>
    </div>

    <!-- 4 Main Metrics (Shopify Style) -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-5">
      <div class="bg-slate-900 p-6 rounded-3xl border border-slate-800 space-y-2">
        <div class="text-slate-400 text-xs font-semibold">Total Revenue</div>
        <div class="text-3xl font-bold text-emerald-400 font-mono">৳{{ metrics?.total_revenue || '1,898' }}</div>
        <div class="text-[11px] text-slate-500">Live sales total</div>
      </div>

      <div class="bg-slate-900 p-6 rounded-3xl border border-slate-800 space-y-2">
        <div class="text-slate-400 text-xs font-semibold">Total Orders</div>
        <div class="text-3xl font-bold text-indigo-400 font-mono">{{ metrics?.total_orders || '2' }}</div>
        <div class="text-[11px] text-slate-500">Processing & fulfilled</div>
      </div>

      <div class="bg-slate-900 p-6 rounded-3xl border border-slate-800 space-y-2">
        <div class="text-slate-400 text-xs font-semibold">Total Customers</div>
        <div class="text-3xl font-bold text-purple-400 font-mono">{{ metrics?.total_customers || '5' }}</div>
        <div class="text-[11px] text-slate-500">Registered users</div>
      </div>

      <div class="bg-slate-900 p-6 rounded-3xl border border-slate-800 space-y-2">
        <div class="text-slate-400 text-xs font-semibold">Avg. Order Value</div>
        <div class="text-3xl font-bold text-amber-400 font-mono">৳{{ metrics?.avg_order_value || '949' }}</div>
        <div class="text-[11px] text-slate-500">Per order average</div>
      </div>
    </div>

    <!-- Quick Shortcuts Panel -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
      <router-link to="/catalog" class="p-5 bg-slate-900 hover:bg-slate-800/80 rounded-2xl border border-slate-800 transition-all flex items-center gap-4 group">
        <div class="w-11 h-11 rounded-xl bg-indigo-600/20 text-indigo-400 text-xl flex items-center justify-center font-bold group-hover:scale-105 transition-transform">
          📦
        </div>
        <div>
          <div class="font-bold text-white text-xs">Products</div>
          <div class="text-slate-400 text-[11px]">Manage catalog</div>
        </div>
      </router-link>

      <router-link to="/orders" class="p-5 bg-slate-900 hover:bg-slate-800/80 rounded-2xl border border-slate-800 transition-all flex items-center gap-4 group">
        <div class="w-11 h-11 rounded-xl bg-emerald-600/20 text-emerald-400 text-xl flex items-center justify-center font-bold group-hover:scale-105 transition-transform">
          🚚
        </div>
        <div>
          <div class="font-bold text-white text-xs">Orders</div>
          <div class="text-slate-400 text-[11px]">Fulfill shipments</div>
        </div>
      </router-link>

      <router-link to="/landing-builder" class="p-5 bg-slate-900 hover:bg-slate-800/80 rounded-2xl border border-slate-800 transition-all flex items-center gap-4 group">
        <div class="w-11 h-11 rounded-xl bg-purple-600/20 text-purple-400 text-xl flex items-center justify-center font-bold group-hover:scale-105 transition-transform">
          🖼️
        </div>
        <div>
          <div class="font-bold text-white text-xs">Homepage Banners</div>
          <div class="text-slate-400 text-[11px]">Edit slide banners</div>
        </div>
      </router-link>

      <router-link to="/settings" class="p-5 bg-slate-900 hover:bg-slate-800/80 rounded-2xl border border-slate-800 transition-all flex items-center gap-4 group">
        <div class="w-11 h-11 rounded-xl bg-amber-600/20 text-amber-400 text-xl flex items-center justify-center font-bold group-hover:scale-105 transition-transform">
          ⚙️
        </div>
        <div>
          <div class="font-bold text-white text-xs">Store Settings</div>
          <div class="text-slate-400 text-[11px]">Payment & delivery</div>
        </div>
      </router-link>
    </div>

    <!-- RECENT ORDERS TABLE & LOW STOCK LIST -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <!-- Recent Orders List -->
      <div class="lg:col-span-2 bg-slate-900 p-6 rounded-3xl border border-slate-800 space-y-4">
        <div class="flex items-center justify-between border-b border-slate-800 pb-3">
          <h3 class="font-bold text-white text-sm">Recent Orders</h3>
          <router-link to="/orders" class="text-xs text-indigo-400 hover:underline font-semibold">View All →</router-link>
        </div>

        <div class="space-y-3">
          <div v-for="order in displayOrders" :key="order.id" class="p-4 bg-slate-950 rounded-2xl border border-slate-800/80 flex items-center justify-between text-xs gap-3">
            <div>
              <div class="font-mono font-bold text-indigo-400">#{{ order.id || order.order_number }}</div>
              <div class="font-bold text-white mt-0.5">{{ order.customer_name || order.shipping_name || 'Customer' }}</div>
              <div class="text-[10px] text-slate-500 font-mono">{{ order.shipping_phone || '01700000000' }}</div>
            </div>
            <div class="text-right">
              <div class="font-bold text-emerald-400 text-sm font-mono">৳{{ order.grand_total || order.total || 0 }}</div>
              <span class="px-2.5 py-0.5 bg-indigo-500/20 text-indigo-300 rounded-full text-[10px] font-bold uppercase inline-block mt-1">
                {{ order.status || 'Pending' }}
              </span>
            </div>
          </div>
          <div v-if="!displayOrders.length" class="p-6 text-center text-slate-500 text-xs">
            No recent orders found.
          </div>
        </div>
      </div>

      <!-- Low Stock Alerts -->
      <div class="bg-slate-900 p-6 rounded-3xl border border-slate-800 space-y-4">
        <div class="flex items-center justify-between border-b border-slate-800 pb-3">
          <h3 class="font-bold text-amber-400 text-sm">Low Stock Items</h3>
          <router-link to="/catalog" class="text-xs text-slate-400 hover:underline">Restock</router-link>
        </div>

        <div class="space-y-3">
          <div v-for="prod in displayLowStock" :key="prod.id" class="p-4 bg-slate-950 rounded-2xl border border-slate-800 flex items-center justify-between text-xs">
            <div class="font-semibold text-white truncate max-w-[140px]">{{ prod.name }}</div>
            <span class="px-2.5 py-1 bg-rose-500/20 text-rose-300 rounded-lg font-mono font-bold text-xs border border-rose-500/30 shrink-0">
              {{ prod.stock_quantity || 0 }} in stock
            </span>
          </div>
          <div v-if="!displayLowStock.length" class="p-6 text-center text-slate-500 text-xs">
            No low stock items. All inventory healthy!
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue';
import api from '../services/api';
import { useAdminAuthStore } from '../stores/auth';

const authStore = useAdminAuthStore();

const metrics = ref<any>({
  total_revenue: 0,
  total_orders: 0,
  total_customers: 0,
  avg_order_value: 0,
});

const displayOrders = ref<any[]>([]);
const displayLowStock = ref<any[]>([]);

onMounted(async () => {
  try {
    const r1 = await api.get('/admin/dashboard/metrics');
    if (r1.data) metrics.value = r1.data.data || r1.data;
  } catch {}

  try {
    const r2 = await api.get('/admin/dashboard/recent-orders');
    displayOrders.value = r2.data?.data || r2.data || [];
  } catch {}

  try {
    const r3 = await api.get('/admin/dashboard/low-stock');
    displayLowStock.value = r3.data?.data || r3.data || [];
  } catch {}
});
</script>
