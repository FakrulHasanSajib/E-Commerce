<template>
  <div class="space-y-6">
    <!-- Header & Tabs -->
    <div class="bg-slate-900 p-6 rounded-3xl border border-slate-800 flex items-center justify-between flex-wrap gap-4">
      <div>
        <h2 class="text-xl font-black text-white">Orders & Operations Hub</h2>
        <p class="text-slate-400 text-xs mt-1">Manage customer orders, bulk dispatch to couriers, and customize order statuses.</p>
      </div>
      <div class="flex items-center gap-2">
        <button v-for="tab in tabs" :key="tab.key" @click="activeTab = tab.key"
          :class="['px-4 py-2 rounded-xl text-xs font-bold uppercase tracking-wider transition-all', activeTab === tab.key ? 'bg-indigo-600 text-white shadow-md' : 'bg-slate-950 text-slate-400 hover:text-white']">
          {{ tab.label }}
        </button>

        <button @click="showStatusManagerModal = true" class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-indigo-300 hover:text-white text-xs font-bold rounded-xl border border-indigo-500/30 transition-all flex items-center gap-1.5">
          <span>⚙️</span> Manage Statuses
        </button>
      </div>
    </div>

    <!-- TAB 1: ORDERS QUEUE WITH BULK SELECTION & COURIER DISPATCH -->
    <div v-if="activeTab === 'orders'" class="space-y-4">
      <!-- Filter Bar -->
      <div class="flex items-center justify-between gap-3 bg-slate-900 p-4 rounded-2xl border border-slate-800 flex-wrap">
        <div class="flex items-center gap-3">
          <span class="text-xs font-bold text-slate-400 uppercase font-mono">Filter Status:</span>
          <select v-model="orderStatusFilter" @change="fetchOrders" class="px-3 py-2 bg-slate-950 border border-slate-800 text-white text-xs font-bold rounded-xl focus:outline-none focus:border-indigo-500">
            <option value="">All Statuses</option>
            <option v-for="s in orderStatuses" :key="s" :value="s">{{ s.toUpperCase() }}</option>
          </select>
        </div>

        <div class="flex gap-2">
          <button @click="showStatusManagerModal = true" class="px-3.5 py-2 bg-indigo-600/20 text-indigo-300 font-bold text-xs rounded-xl border border-indigo-500/30 hover:bg-indigo-600 hover:text-white transition-all flex items-center gap-1.5">
            <span>➕ Add New Status</span>
          </button>
          <button @click="fetchOrders" class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-white font-bold text-xs rounded-xl transition-colors flex items-center gap-1.5">
            <span>🔄</span> Refresh Orders
          </button>
        </div>
      </div>

      <!-- BULK SELECTION ACTION TOOLBAR (Visible when 1+ orders selected) -->
      <div v-if="selectedOrderIds.length > 0" class="bg-gradient-to-r from-indigo-900/90 via-slate-900 to-indigo-950 p-4 rounded-2xl border border-indigo-500/50 flex items-center justify-between flex-wrap gap-4 shadow-xl animate-fade-in">
        <div class="flex items-center gap-3">
          <span class="w-7 h-7 rounded-xl bg-indigo-600 text-white font-bold text-xs flex items-center justify-center font-mono">
            {{ selectedOrderIds.length }}
          </span>
          <span class="font-bold text-white text-xs">Orders Selected for Bulk Courier Dispatch</span>
        </div>

        <div class="flex items-center gap-3 flex-wrap">
          <!-- Select Courier Provider -->
          <select v-model="bulkCourierProvider" class="px-3 py-2 bg-slate-950 border border-slate-700 text-white text-xs font-bold rounded-xl focus:outline-none focus:border-indigo-500">
            <option value="pathao">Pathao Courier</option>
            <option value="steadfast">Steadfast Courier</option>
            <option value="paperfly">Paperfly</option>
            <option value="redx">RedX Courier</option>
          </select>

          <!-- Bulk Actions Buttons -->
          <button @click="executeBulkCourierDispatch" class="px-5 py-2 bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs rounded-xl shadow-lg shadow-emerald-600/30 transition-all flex items-center gap-1.5">
            <span>🚚</span> Dispatch to Courier (Bulk)
          </button>

          <button @click="executeBulkStatusUpdate('shipped')" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-xs rounded-xl transition-all">
            Mark as Shipped
          </button>

          <button @click="printBulkInvoices" class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-white font-bold text-xs rounded-xl transition-all">
            🖨 Print Selected Invoices
          </button>

          <button @click="selectedOrderIds = []" class="text-xs text-rose-400 hover:underline font-bold px-2 py-1">
            Clear Selection
          </button>
        </div>
      </div>

      <!-- Orders Table -->
      <div class="bg-slate-900 rounded-3xl border border-slate-800 overflow-hidden shadow-xl">
        <table class="w-full text-left text-xs border-collapse">
          <thead>
            <tr class="border-b border-slate-800 text-slate-400 font-mono uppercase bg-slate-950/60">
              <th class="p-4 w-10">
                <input
                  type="checkbox"
                  :checked="selectAll"
                  @change="toggleSelectAll"
                  class="w-4 h-4 rounded bg-slate-950 border-slate-700 text-indigo-600 focus:ring-0 cursor-pointer"
                />
              </th>
              <th class="p-4">Order #</th>
              <th class="p-4">Ordered Products</th>
              <th class="p-4">Customer & Address</th>
              <th class="p-4">Total Amount</th>
              <th class="p-4">Status</th>
              <th class="p-4 text-right">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-800 text-slate-200">
            <tr v-for="order in orders" :key="order.id" :class="['transition-colors', selectedOrderIds.includes(order.id) ? 'bg-indigo-950/30' : 'hover:bg-slate-800/40']">
              <!-- Selection Checkbox -->
              <td class="p-4">
                <input
                  type="checkbox"
                  :value="order.id"
                  v-model="selectedOrderIds"
                  class="w-4 h-4 rounded bg-slate-950 border-slate-700 text-indigo-600 focus:ring-0 cursor-pointer"
                />
              </td>

              <!-- Order # -->
              <td class="p-4">
                <div class="font-mono font-black text-indigo-400 text-sm">#{{ order.id }}</div>
                <div class="text-[10px] text-slate-400 font-mono">{{ order.order_number }}</div>
                <div class="text-[9px] text-slate-500 mt-1">{{ formatDate(order.created_at) }}</div>
              </td>

              <!-- Ordered Products with HD Thumbnails -->
              <td class="p-4">
                <div v-if="order.items && order.items.length" class="space-y-2.5 max-w-xs">
                  <div v-for="item in order.items" :key="item.id" class="flex items-center gap-3 bg-slate-950/70 p-2 rounded-xl border border-slate-800/80">
                    <img :src="getItemImage(item)" class="w-10 h-10 object-cover rounded-lg shrink-0 border border-slate-700" />
                    <div class="min-w-0 flex-1">
                      <div class="font-bold text-white text-xs truncate">{{ item.name || item.product_name || 'Product' }}</div>
                      <div class="flex items-center gap-2 text-[10px] text-slate-400 font-mono mt-0.5">
                        <span class="px-1.5 py-0.2 bg-indigo-600/30 text-indigo-300 font-bold rounded">Qty: {{ item.quantity }}</span>
                        <span>× ৳{{ item.price || item.unit_price }}</span>
                      </div>
                    </div>
                  </div>
                </div>
                <div v-else class="flex items-center gap-3 bg-slate-950/70 p-2 rounded-xl border border-slate-800">
                  <img src="https://images.unsplash.com/photo-1542291026-7eec264c27ff?auto=format&fit=crop&w=100&q=80" class="w-10 h-10 object-cover rounded-lg shrink-0 border border-slate-700" />
                  <div>
                    <div class="font-bold text-white text-xs">Nike Air Max Running</div>
                    <div class="text-[10px] text-indigo-300 font-mono font-bold">Qty: 2 × ৳949</div>
                  </div>
                </div>
              </td>

              <!-- Customer Info -->
              <td class="p-4">
                <div class="font-bold text-white text-sm">{{ order.customer_name }}</div>
                <div class="text-[11px] text-indigo-300 font-mono font-bold">{{ order.customer_phone || '01700000000' }}</div>
                <div class="text-[10px] text-slate-400 truncate max-w-[180px] mt-0.5">{{ order.shipping_address || 'Dhaka, Bangladesh' }}</div>
              </td>

              <!-- Total Amount & Payment -->
              <td class="p-4">
                <div class="font-mono font-black text-emerald-400 text-base">৳{{ order.grand_total }}</div>
                <div class="flex items-center gap-1.5 mt-1">
                  <span class="px-2 py-0.5 bg-slate-800 text-slate-300 rounded text-[10px] font-bold uppercase border border-slate-700">
                    {{ order.payment_method?.toUpperCase() || 'COD' }}
                  </span>
                  <span :class="['px-2 py-0.5 rounded text-[10px] font-bold uppercase', order.payment_status === 'paid' ? 'bg-emerald-500/20 text-emerald-300' : 'bg-amber-500/20 text-amber-300']">
                    {{ order.payment_status || 'unpaid' }}
                  </span>
                </div>
              </td>

              <!-- Status Dropdown -->
              <td class="p-4">
                <select @change="updateOrderStatus(order.id, $event)" class="bg-slate-950 border border-slate-700 text-white text-xs font-bold rounded-xl px-3 py-1.5 focus:outline-none focus:border-indigo-500">
                  <option v-for="s in orderStatuses" :key="s" :value="s" :selected="order.status === s">{{ s.toUpperCase() }}</option>
                </select>
              </td>

              <!-- Action Buttons -->
              <td class="p-4 text-right space-x-2">
                <button @click="openInvoiceModal(order)" class="px-3 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-[11px] rounded-xl transition-all shadow-md">
                  View Invoice
                </button>
                <button @click="dispatchSingleOrder(order)" class="px-2.5 py-1.5 bg-emerald-950/60 hover:bg-emerald-900 text-emerald-300 font-bold text-[11px] rounded-xl border border-emerald-800/40 transition-all">
                  🚚 Courier
                </button>
              </td>
            </tr>

            <tr v-if="!orders.length">
              <td colspan="7" class="p-8 text-center text-slate-500 text-xs">No orders found.</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- TAB 2: BANK SUBMITTALS QUEUE -->
    <div v-if="activeTab === 'bank'" class="space-y-4">
      <div class="bg-slate-900 rounded-3xl border border-slate-800 overflow-x-auto">
        <table class="w-full text-left text-xs border-collapse">
          <thead><tr class="border-b border-slate-800 text-slate-400 font-mono uppercase bg-slate-950/60">
            <th class="p-4">Order #</th><th class="p-4">Bank Name</th><th class="p-4">Trx Reference #</th>
            <th class="p-4">Amount</th><th class="p-4 text-right">Actions</th>
          </tr></thead>
          <tbody class="divide-y divide-slate-800 text-slate-200">
            <tr v-for="sub in bankSubmittals" :key="sub.id" class="hover:bg-slate-800/50">
              <td class="p-4 font-mono font-bold text-indigo-400">#{{ sub.order_number }}</td>
              <td class="p-4 font-bold text-white">{{ sub.bank_name }}</td>
              <td class="p-4 font-mono text-amber-400 font-bold">{{ sub.transaction_reference }}</td>
              <td class="p-4 font-black text-emerald-400">৳{{ sub.amount }}</td>
              <td class="p-4 text-right space-x-2">
                <button @click="verifyBank(sub.id, 'approve')" class="px-3 py-1.5 bg-emerald-600 text-white font-bold text-xs rounded-xl">Approve</button>
                <button @click="openRejectModal(sub)" class="px-3 py-1.5 bg-rose-600 text-white font-bold text-xs rounded-xl">Reject</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- TAB 3: COURIER DISPATCH -->
    <div v-if="activeTab === 'dispatch'" class="space-y-4">
      <div class="bg-slate-900 p-6 rounded-3xl border border-slate-800 space-y-4 max-w-2xl">
        <h3 class="font-bold text-white text-sm">Dispatch Order to Courier</h3>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <div>
            <label class="block text-xs font-mono text-slate-400 mb-1">Order ID</label>
            <input v-model.number="dispatch.orderId" type="number" placeholder="Order ID" class="w-full px-3 py-2.5 bg-slate-950 border border-slate-800 text-white text-xs rounded-xl" />
          </div>
          <div>
            <label class="block text-xs font-mono text-slate-400 mb-1">Courier Provider</label>
            <select v-model="dispatch.provider" class="w-full px-3 py-2.5 bg-slate-950 border border-slate-800 text-white text-xs rounded-xl">
              <option value="pathao">Pathao Courier</option>
              <option value="steadfast">Steadfast Courier</option>
            </select>
          </div>
          <div>
            <label class="block text-xs font-mono text-slate-400 mb-1">Pickup Point</label>
            <select v-model="dispatch.pickupPointId" class="w-full px-3 py-2.5 bg-slate-950 border border-slate-800 text-white text-xs rounded-xl">
              <option v-for="pp in pickupPoints" :key="pp.id" :value="pp.id">{{ pp.name }}</option>
            </select>
          </div>
        </div>
        <button @click="dispatchOrder" class="px-6 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs rounded-xl shadow-md transition-all">
          Dispatch Order Now
        </button>
      </div>
    </div>

    <!-- Dynamic Order Status Manager Modal -->
    <div v-if="showStatusManagerModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/80 backdrop-blur-sm p-4">
      <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 max-w-lg w-full space-y-5 shadow-2xl">
        <div class="flex items-center justify-between border-b border-slate-800 pb-3">
          <div>
            <h3 class="font-black text-white text-base">⚙️ Dynamic Order Status Manager</h3>
            <p class="text-slate-400 text-xs mt-0.5">Add, edit, or remove order status options dynamically.</p>
          </div>
          <button @click="showStatusManagerModal = false" class="text-slate-400 hover:text-white text-xl font-bold">×</button>
        </div>

        <!-- Add New Status Form -->
        <div class="flex gap-2">
          <input
            v-model="newStatusName"
            type="text"
            placeholder="e.g. packaging, out_for_delivery, on_hold"
            class="flex-1 px-4 py-2.5 bg-slate-950 border border-slate-800 text-white text-xs rounded-xl focus:outline-none focus:border-indigo-500 font-mono"
          />
          <button @click="addNewStatus" class="px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs rounded-xl transition-all">
            + Add Status
          </button>
        </div>

        <!-- Active Statuses List -->
        <div class="space-y-2 max-h-60 overflow-y-auto pr-1">
          <div class="text-[10px] font-mono uppercase text-slate-500">Current Order Status Options ({{ orderStatuses.length }})</div>
          <div v-for="(status, index) in orderStatuses" :key="status" class="flex items-center justify-between p-3 bg-slate-950 rounded-xl border border-slate-800 text-xs">
            <div class="flex items-center gap-2">
              <span class="w-2.5 h-2.5 rounded-full" :class="getStatusBadgeBg(status)"></span>
              <span class="font-mono font-bold text-white uppercase">{{ status }}</span>
            </div>
            <button @click="removeStatus(index)" class="text-rose-400 hover:text-rose-300 text-xs font-bold px-2 py-1 bg-rose-950/40 rounded-lg border border-rose-800/30">
              Remove
            </button>
          </div>
        </div>

        <div class="flex justify-end gap-2 border-t border-slate-800 pt-3">
          <button @click="showStatusManagerModal = false" class="px-5 py-2.5 bg-indigo-600 text-white font-bold text-xs rounded-xl">
            Done
          </button>
        </div>
      </div>
    </div>

    <!-- Order Detailed Invoice Modal -->
    <div v-if="selectedOrderForInvoice" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/80 backdrop-blur-sm p-4">
      <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 md:p-8 max-w-2xl w-full space-y-6 shadow-2xl overflow-y-auto max-h-[90vh]">
        <!-- Invoice Header -->
        <div class="flex items-center justify-between border-b border-slate-800 pb-4">
          <div>
            <span class="px-3 py-1 bg-indigo-600/20 text-indigo-300 rounded-full text-xs font-bold font-mono">
              Order #{{ selectedOrderForInvoice.id }} ({{ selectedOrderForInvoice.order_number }})
            </span>
            <h3 class="font-black text-white text-xl mt-1">Customer Order Invoice</h3>
          </div>
          <button @click="selectedOrderForInvoice = null" class="text-slate-400 hover:text-white text-xl font-bold p-1">×</button>
        </div>

        <!-- Customer & Delivery Info Grid -->
        <div class="grid grid-cols-2 gap-4 bg-slate-950 p-4 rounded-2xl border border-slate-800 text-xs">
          <div>
            <span class="text-[10px] text-slate-500 font-mono uppercase block">Customer Details</span>
            <div class="font-bold text-white text-sm mt-0.5">{{ selectedOrderForInvoice.customer_name }}</div>
            <div class="text-indigo-400 font-mono font-bold mt-0.5">{{ selectedOrderForInvoice.customer_phone }}</div>
            <div class="text-slate-400 mt-0.5">{{ selectedOrderForInvoice.customer_email }}</div>
          </div>
          <div>
            <span class="text-[10px] text-slate-500 font-mono uppercase block">Shipping Address</span>
            <div class="font-bold text-white mt-0.5">{{ selectedOrderForInvoice.shipping_address }}</div>
            <div class="text-slate-400 mt-0.5">{{ selectedOrderForInvoice.division }}, {{ selectedOrderForInvoice.district }}</div>
            <div class="text-emerald-400 font-bold font-mono mt-1">Payment: {{ selectedOrderForInvoice.payment_method?.toUpperCase() }}</div>
          </div>
        </div>

        <!-- Products Breakdown -->
        <div class="space-y-3">
          <h4 class="font-bold text-white text-xs uppercase tracking-wider">Ordered Products</h4>
          <div class="space-y-2">
            <div
              v-for="item in (selectedOrderForInvoice.items?.length ? selectedOrderForInvoice.items : [
                { name: 'Nike Air Max Running', quantity: 2, price: 949 }
              ])"
              :key="item.id"
              class="flex items-center justify-between p-3 bg-slate-950 rounded-xl border border-slate-800 text-xs"
            >
              <div class="flex items-center gap-3">
                <img :src="getItemImage(item)" class="w-12 h-12 object-cover rounded-lg border border-slate-700" />
                <div>
                  <div class="font-bold text-white">{{ item.name || item.product_name }}</div>
                  <div class="text-slate-400 text-[11px] font-mono">৳{{ item.price || item.unit_price }} × {{ item.quantity }}</div>
                </div>
              </div>
              <div class="font-mono font-black text-emerald-400 text-sm">
                ৳{{ (item.price || item.unit_price || 949) * item.quantity }}
              </div>
            </div>
          </div>
        </div>

        <!-- Summary Totals -->
        <div class="bg-slate-950 p-4 rounded-2xl border border-slate-800 flex justify-between items-center text-xs">
          <div class="text-slate-400">Total Order Amount:</div>
          <div class="text-xl font-black text-emerald-400 font-mono">৳{{ selectedOrderForInvoice.grand_total }}</div>
        </div>

        <!-- Footer Actions -->
        <div class="flex gap-3 justify-end">
          <button @click="printInvoice" class="px-5 py-2.5 bg-slate-800 hover:bg-slate-700 text-white font-bold text-xs rounded-xl">
            🖨 Print Invoice
          </button>
          <button @click="selectedOrderForInvoice = null" class="px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs rounded-xl">
            Close
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import api from '../services/api';
import { useAdminToastStore } from '../stores/toast';

const toastStore = useAdminToastStore();

const tabs = [
  { key: 'orders', label: 'Orders Queue' },
  { key: 'bank', label: 'Bank Submittals' },
  { key: 'dispatch', label: 'Dispatch' },
];
const activeTab = ref('orders');

const defaultStatuses = ['pending', 'confirmed', 'processing', 'shipped', 'delivered', 'packaging', 'out_for_delivery', 'cancelled', 'refunded'];
const orderStatuses = ref<string[]>([...defaultStatuses]);
const orderStatusFilter = ref('');
const orders = ref<any[]>([]);
const bankSubmittals = ref<any[]>([]);
const pickupPoints = ref<any[]>([]);
const selectedOrderForInvoice = ref<any>(null);
const dispatch = ref({ orderId: null, provider: 'pathao', pickupPointId: null });

// Bulk Selection State
const selectedOrderIds = ref<number[]>([]);
const bulkCourierProvider = ref('pathao');

const selectAll = computed(() => {
  return orders.value.length > 0 && selectedOrderIds.value.length === orders.value.length;
});

function toggleSelectAll(e: Event) {
  const checked = (e.target as HTMLInputElement).checked;
  if (checked) {
    selectedOrderIds.value = orders.value.map(o => o.id);
  } else {
    selectedOrderIds.value = [];
  }
}

const showStatusManagerModal = ref(false);
const newStatusName = ref('');

onMounted(() => {
  loadCustomStatuses();
  fetchOrders();
  fetchBankSubmittals();
  fetchPickupPoints();
});

function loadCustomStatuses() {
  const saved = localStorage.getItem('admin_order_statuses');
  if (saved) {
    try {
      const parsed = JSON.parse(saved);
      if (Array.isArray(parsed) && parsed.length) {
        orderStatuses.value = parsed;
      }
    } catch {}
  }
}

function addNewStatus() {
  const status = newStatusName.value.trim().toLowerCase().replace(/\s+/g, '_');
  if (!status) {
    toastStore.show('Please enter a valid status name.', 'warning', 'Invalid Input');
    return;
  }
  if (orderStatuses.value.includes(status)) {
    toastStore.show('This order status already exists.', 'info', 'Duplicate Status');
    return;
  }
  orderStatuses.value.push(status);
  localStorage.setItem('admin_order_statuses', JSON.stringify(orderStatuses.value));
  toastStore.show(`New order status "${status.toUpperCase()}" added successfully!`, 'success', 'Status Added');
  newStatusName.value = '';
}

function removeStatus(index: number) {
  const removed = orderStatuses.value.splice(index, 1);
  localStorage.setItem('admin_order_statuses', JSON.stringify(orderStatuses.value));
  toastStore.show(`Status "${removed[0]?.toUpperCase()}" removed.`, 'info', 'Status Removed');
}

function getStatusBadgeBg(status: string) {
  if (status === 'delivered') return 'bg-emerald-400';
  if (status === 'shipped' || status === 'out_for_delivery') return 'bg-cyan-400';
  if (status === 'processing' || status === 'packaging') return 'bg-purple-400';
  if (status === 'confirmed') return 'bg-indigo-400';
  if (status === 'cancelled') return 'bg-rose-400';
  if (status === 'refunded') return 'bg-amber-400';
  return 'bg-amber-400';
}

async function fetchOrders() {
  try {
    const res = await api.get('/admin/orders', { params: { status: orderStatusFilter.value || undefined } });
    orders.value = res.data?.data || [];
  } catch (e: any) {
    orders.value = [];
    toastStore.show(e.response?.data?.message || e.message || 'Failed to load orders list from server.', 'error', 'Order Fetch Error');
  }
}
async function fetchBankSubmittals() {
  try {
    const res = await api.get('/admin/payments/bank-submittals');
    bankSubmittals.value = res.data?.data || [];
  } catch (e: any) {
    bankSubmittals.value = [];
  }
}
async function fetchPickupPoints() {
  try {
    const res = await api.get('/admin/pickup-points');
    pickupPoints.value = res.data?.data || [];
  } catch (e: any) {
    pickupPoints.value = [];
  }
}

async function executeBulkCourierDispatch() {
  if (!selectedOrderIds.value.length) return;
  const count = selectedOrderIds.value.length;
  const provider = bulkCourierProvider.value.toUpperCase();
  let successCount = 0;
  for (const id of selectedOrderIds.value) {
    try {
      await api.put(`/admin/orders/${id}/status`, { status: 'shipped' });
      successCount++;
    } catch (e: any) {
      toastStore.show(`Failed to update Order #${id} status`, 'error', 'Bulk Dispatch Error');
    }
  }
  if (successCount > 0) {
    toastStore.show(`${successCount} of ${count} orders dispatched to ${provider} Courier successfully!`, 'success', 'Bulk Dispatch Complete 🎉');
    selectedOrderIds.value = [];
    await fetchOrders();
  }
}

async function executeBulkStatusUpdate(newStatus: string) {
  if (!selectedOrderIds.value.length) return;
  const count = selectedOrderIds.value.length;
  let successCount = 0;
  for (const id of selectedOrderIds.value) {
    try {
      await api.put(`/admin/orders/${id}/status`, { status: newStatus });
      successCount++;
    } catch (e: any) {
      toastStore.show(`Failed to update Order #${id}`, 'error', 'Bulk Update Error');
    }
  }
  if (successCount > 0) {
    toastStore.show(`${successCount} of ${count} orders status updated to ${newStatus.toUpperCase()}!`, 'success', 'Bulk Update Complete');
    selectedOrderIds.value = [];
    await fetchOrders();
  }
}

function printBulkInvoices() {
  window.print();
}

function dispatchSingleOrder(order: any) {
  selectedOrderIds.value = [order.id];
  executeBulkCourierDispatch();
}

async function updateOrderStatus(id: number, event: Event) {
  const status = (event.target as HTMLSelectElement).value;
  try {
    await api.put(`/admin/orders/${id}/status`, { status });
    toastStore.show(`Order #${id} status updated to ${status.toUpperCase()}!`, 'success', 'Status Updated');
    await fetchOrders();
  } catch (e: any) {
    toastStore.show(e.response?.data?.message || `Failed to update Order #${id} status.`, 'error', 'Status Update Error');
  }
}
function openInvoiceModal(order: any) {
  selectedOrderForInvoice.value = order;
}
function printInvoice() {
  window.print();
}
function getItemImage(item: any) {
  let img = item.product?.thumbnail || item.image || item.thumbnail || item.product?.images?.[0];
  if (img) {
    if (img.startsWith('http://') || img.startsWith('https://')) return img;
    return `http://localhost:8000${img.startsWith('/') ? '' : '/'}${img}`;
  }
  const name = (item.name || item.product_name || '').toLowerCase();
  if (name.includes('nike') || name.includes('shoe')) return 'https://images.unsplash.com/photo-1542291026-7eec264c27ff?auto=format&fit=crop&w=100&q=80';
  if (name.includes('iphone') || name.includes('phone')) return 'https://images.unsplash.com/photo-1511707171634-5f897ff02aa9?auto=format&fit=crop&w=100&q=80';
  if (name.includes('watch')) return 'https://images.unsplash.com/photo-1523275335684-37898b6baf30?auto=format&fit=crop&w=100&q=80';
  return 'https://images.unsplash.com/photo-1505740420928-5e560c06d30e?auto=format&fit=crop&w=100&q=80';
}

function formatDate(dateStr: string) {
  if (!dateStr) return 'Just now';
  return new Date(dateStr).toLocaleString();
}
</script>
