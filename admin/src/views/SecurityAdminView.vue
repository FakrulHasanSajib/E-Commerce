<template>
  <div class="space-y-6">
    <div class="bg-slate-900 p-6 rounded-2xl border border-slate-800 flex items-center justify-between">
      <div>
        <h2 class="text-xl font-black text-white">Security & Fraud Prevention</h2>
        <p class="text-slate-400 text-xs mt-1">Audit logs, IP blacklists, flagged orders, device sessions, 2FA management.</p>
      </div>
      <div class="flex gap-2 flex-wrap">
        <button v-for="tab in tabs" :key="tab.key" @click="activeTab = tab.key"
          :class="['px-3 py-2 rounded-xl text-xs font-bold uppercase transition-all', activeTab === tab.key ? 'bg-indigo-600 text-white' : 'bg-slate-950 text-slate-400 hover:text-white']">
          {{ tab.label }}
        </button>
      </div>
    </div>

    <!-- TAB 1: AUDIT LOGS -->
    <div v-if="activeTab === 'audit'" class="bg-slate-900 p-6 rounded-2xl border border-slate-800 space-y-4">
      <h3 class="font-bold text-white text-sm border-b border-slate-800 pb-3">System Audit Event Log</h3>
      <div class="space-y-2 max-h-[60vh] overflow-y-auto">
        <div v-for="log in auditLogs" :key="log.id" class="flex items-start justify-between p-3 bg-slate-950 rounded-xl border border-slate-800 text-xs gap-4">
          <div>
            <div class="font-mono font-bold text-indigo-400">{{ log.event }}</div>
            <div class="text-slate-400 text-[10px] mt-0.5">User: {{ log.user?.name || 'System' }} • Model: {{ log.auditable_type }}</div>
          </div>
          <div class="text-slate-500 font-mono text-[10px] shrink-0">{{ log.created_at }}</div>
        </div>
        <div v-if="!auditLogs.length" class="text-center py-8 text-slate-500 text-xs">No audit events recorded yet.</div>
      </div>
    </div>

    <!-- TAB 2: BLACKLISTS -->
    <div v-if="activeTab === 'blacklists'" class="bg-slate-900 p-6 rounded-2xl border border-slate-800 space-y-4">
      <div class="flex items-center justify-between border-b border-slate-800 pb-3">
        <h3 class="font-bold text-white text-sm">IP / Email Blacklist Registry</h3>
        <button @click="addBlacklist" class="px-3 py-1.5 bg-rose-600 text-white font-bold text-[10px] rounded-lg">+ Add to Blacklist</button>
      </div>
      <div class="space-y-2">
        <div v-for="bl in blacklists" :key="bl.id" class="flex items-center justify-between p-3 bg-slate-950 rounded-xl border border-slate-800 text-xs">
          <div>
            <div class="font-mono font-bold text-rose-400">{{ bl.value }}</div>
            <div class="text-slate-400">Type: {{ bl.type }} • Reason: {{ bl.reason }}</div>
          </div>
          <button @click="removeBlacklist(bl.id)" class="text-slate-400 font-bold hover:text-rose-400">Remove</button>
        </div>
        <div v-if="!blacklists.length" class="text-center py-6 text-slate-500 text-xs">Blacklist is empty.</div>
      </div>
    </div>

    <!-- TAB 3: FLAGGED ORDERS -->
    <div v-if="activeTab === 'flagged'" class="bg-slate-900 p-6 rounded-2xl border border-slate-800 space-y-4">
      <h3 class="font-bold text-white text-sm border-b border-slate-800 pb-3">Fraud-Flagged Orders Queue</h3>
      <div class="overflow-x-auto">
        <table class="w-full text-left text-xs border-collapse">
          <thead><tr class="border-b border-slate-800 text-slate-400 font-mono uppercase">
            <th class="p-4">Order #</th><th class="p-4">Customer</th><th class="p-4">Risk Score</th><th class="p-4">Reason</th>
          </tr></thead>
          <tbody class="divide-y divide-slate-800 text-slate-200">
            <tr v-for="o in flaggedOrders" :key="o.id" class="hover:bg-slate-800/50">
              <td class="p-4 font-mono text-rose-400">{{ o.order_number }}</td>
              <td class="p-4">{{ o.customer_name }}</td>
              <td class="p-4"><span class="font-bold text-rose-400">{{ o.fraud_score }}</span></td>
              <td class="p-4 text-slate-400">{{ o.fraud_reason }}</td>
            </tr>
          </tbody>
        </table>
        <div v-if="!flaggedOrders.length" class="text-center py-8 text-slate-500 text-xs">No flagged orders.</div>
      </div>
    </div>

    <!-- TAB 4: DEVICE SESSIONS -->
    <div v-if="activeTab === 'devices'" class="bg-slate-900 p-6 rounded-2xl border border-slate-800 space-y-4">
      <h3 class="font-bold text-white text-sm border-b border-slate-800 pb-3">Registered Device Sessions</h3>
      <div class="space-y-2">
        <div v-for="d in devices" :key="d.id" class="flex items-center justify-between p-3 bg-slate-950 rounded-xl border border-slate-800 text-xs">
          <div>
            <div class="font-bold text-white">{{ d.device_name || 'Unknown Device' }}</div>
            <div class="text-slate-400 text-[10px]">{{ d.user_agent?.slice(0, 60) }}</div>
          </div>
          <span class="font-mono text-[10px] text-slate-400">{{ d.last_active_at || d.created_at }}</span>
        </div>
        <div v-if="!devices.length" class="text-center py-6 text-slate-500 text-xs">No device sessions registered.</div>
      </div>
    </div>

    <!-- TAB 5: 2FA MANAGEMENT -->
    <div v-if="activeTab === '2fa'" class="bg-slate-900 p-6 rounded-2xl border border-slate-800 space-y-6">
      <h3 class="font-bold text-white text-sm border-b border-slate-800 pb-3">Two-Factor Authentication Management</h3>
      <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <button @click="twoFA('enable')" class="p-4 bg-emerald-600/20 border border-emerald-500/30 rounded-xl text-emerald-300 font-bold text-xs hover:bg-emerald-600/30 transition-all">
          ✓ Enable 2FA<br><span class="font-normal text-slate-400 text-[10px]">POST /admin/2fa/enable</span>
        </button>
        <button @click="twoFA('confirm')" class="p-4 bg-indigo-600/20 border border-indigo-500/30 rounded-xl text-indigo-300 font-bold text-xs hover:bg-indigo-600/30 transition-all">
          ⚙ Confirm 2FA<br><span class="font-normal text-slate-400 text-[10px]">POST /admin/2fa/confirm</span>
        </button>
        <button @click="twoFA('disable')" class="p-4 bg-rose-600/20 border border-rose-500/30 rounded-xl text-rose-300 font-bold text-xs hover:bg-rose-600/30 transition-all">
          ✕ Disable 2FA<br><span class="font-normal text-slate-400 text-[10px]">POST /admin/2fa/disable</span>
        </button>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue';
import api from '../services/api';

const tabs = [
  { key: 'audit', label: 'Audit Logs' },
  { key: 'blacklists', label: 'Blacklists' },
  { key: 'flagged', label: 'Flagged Orders' },
  { key: 'devices', label: 'Devices' },
  { key: '2fa', label: '2FA' },
];
const activeTab = ref('audit');
const auditLogs = ref<any[]>([]);
const blacklists = ref<any[]>([]);
const flaggedOrders = ref<any[]>([]);
const devices = ref<any[]>([]);

onMounted(() => { fetchAll(); });

async function fetchAll() {
  try { const r = await api.get('/admin/audit-logs'); auditLogs.value = r.data?.data || []; } catch {}
  try { const r = await api.get('/admin/blacklists'); blacklists.value = r.data?.data || []; } catch {}
  try { const r = await api.get('/admin/flagged-orders'); flaggedOrders.value = r.data?.data || []; } catch {}
  try { const r = await api.get('/admin/devices'); devices.value = r.data?.data || []; } catch {}
}
async function addBlacklist() {
  const value = prompt('IP address or email to blacklist:');
  const reason = prompt('Reason:');
  if (value && reason) {
    const type = value.includes('@') ? 'email' : 'ip';
    await api.post('/admin/blacklists', { value, type, reason });
    fetchAll();
  }
}
async function removeBlacklist(id: number) {
  if (confirm('Remove from blacklist?')) { await api.delete(`/admin/blacklists/${id}`); fetchAll(); }
}
async function twoFA(action: string) {
  const code = action === 'confirm' || action === 'disable' ? prompt('Enter TOTP code:') : null;
  try {
    await api.post(`/admin/2fa/${action}`, code ? { code } : {});
    alert(`2FA ${action} completed!`);
  } catch (e: any) {
    alert(e.response?.data?.message || `2FA ${action} failed.`);
  }
}
</script>
