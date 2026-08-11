<template>
  <div class="space-y-6">
    <div class="bg-slate-900 p-6 rounded-2xl border border-slate-800">
      <h2 class="text-xl font-black text-white">System Health Monitor</h2>
      <p class="text-slate-400 text-xs mt-1">Real-time DB, Redis, Disk, and Queue status indicators.</p>
    </div>

    <div v-if="isLoading" class="grid grid-cols-2 md:grid-cols-4 gap-4">
      <div v-for="i in 4" :key="i" class="h-28 bg-slate-900 rounded-2xl animate-pulse border border-slate-800"></div>
    </div>

    <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
      <div v-for="service in services" :key="service.name"
        :class="['p-6 rounded-2xl border space-y-3', service.status === 'ok' ? 'bg-emerald-900/20 border-emerald-800/50' : service.status === 'warn' ? 'bg-amber-900/20 border-amber-800/50' : 'bg-rose-900/20 border-rose-800/50']">
        <div class="flex items-center justify-between">
          <span class="text-xl">{{ service.icon }}</span>
          <span :class="['w-2.5 h-2.5 rounded-full', service.status === 'ok' ? 'bg-emerald-400 shadow-[0_0_8px_#34d399]' : service.status === 'warn' ? 'bg-amber-400' : 'bg-rose-400 shadow-[0_0_8px_#f87171]']"></span>
        </div>
        <div>
          <div class="font-black text-white text-sm">{{ service.name }}</div>
          <div :class="['text-[11px] font-bold uppercase mt-0.5', service.status === 'ok' ? 'text-emerald-400' : service.status === 'warn' ? 'text-amber-400' : 'text-rose-400']">
            {{ service.status === 'ok' ? 'Healthy' : service.status === 'warn' ? 'Warning' : 'Critical' }}
          </div>
        </div>
        <div class="text-[10px] text-slate-400 font-mono space-y-0.5">
          <div v-for="(val, key) in service.details" :key="key">{{ key }}: {{ val }}</div>
        </div>
      </div>
    </div>

    <!-- Queue Status -->
    <div class="bg-slate-900 p-6 rounded-2xl border border-slate-800 space-y-4">
      <h3 class="font-bold text-white text-sm border-b border-slate-800 pb-3">Queue Worker Status</h3>
      <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-xs">
        <div class="p-3 bg-slate-950 rounded-xl border border-slate-800 text-center">
          <div class="text-slate-400 text-[10px] uppercase font-mono">Pending</div>
          <div class="font-black text-white text-lg">{{ queueStats.pending || 0 }}</div>
        </div>
        <div class="p-3 bg-slate-950 rounded-xl border border-slate-800 text-center">
          <div class="text-slate-400 text-[10px] uppercase font-mono">Processing</div>
          <div class="font-black text-indigo-300 text-lg">{{ queueStats.processing || 0 }}</div>
        </div>
        <div class="p-3 bg-slate-950 rounded-xl border border-slate-800 text-center">
          <div class="text-slate-400 text-[10px] uppercase font-mono">Failed</div>
          <div class="font-black text-rose-400 text-lg">{{ queueStats.failed || 0 }}</div>
        </div>
        <div class="p-3 bg-slate-950 rounded-xl border border-slate-800 text-center">
          <div class="text-slate-400 text-[10px] uppercase font-mono">Completed (24h)</div>
          <div class="font-black text-emerald-400 text-lg">{{ queueStats.completed || 0 }}</div>
        </div>
      </div>
    </div>

    <!-- Refresh Button -->
    <div class="text-center">
      <button @click="fetchHealth" :disabled="isLoading"
        class="px-6 py-2.5 bg-slate-800 hover:bg-slate-700 text-white font-bold text-xs rounded-xl transition-all disabled:opacity-50">
        ↻ Refresh Health Status
      </button>
      <div class="text-slate-500 text-[10px] mt-2 font-mono">Last checked: {{ lastChecked }}</div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue';
import api from '../services/api';

const isLoading = ref(false);
const services = ref<any[]>([]);
const queueStats = ref<any>({});
const lastChecked = ref('—');

onMounted(() => { fetchHealth(); });

async function fetchHealth() {
  isLoading.value = true;
  services.value = [];
  try {
    const res = await api.get('/admin/system/health');
    const h = res.data || {};

    services.value = [
      {
        name: 'Database',
        icon: '🗄️',
        status: h.database?.status || 'ok',
        details: { driver: h.database?.driver || 'mysql', latency: (h.database?.latency_ms || 0) + 'ms' },
      },
      {
        name: 'Redis / Cache',
        icon: '⚡',
        status: h.redis?.status || 'ok',
        details: { memory: h.redis?.used_memory || '—', connected: h.redis?.connected_clients || '—' },
      },
      {
        name: 'Disk Storage',
        icon: '💾',
        status: h.disk?.status || 'ok',
        details: { used: h.disk?.used || '—', free: h.disk?.free || '—', total: h.disk?.total || '—' },
      },
      {
        name: 'Queue Workers',
        icon: '⚙️',
        status: h.queue?.status || 'ok',
        details: { workers: h.queue?.workers || 0, failed: h.queue?.failed || 0 },
      },
    ];

    queueStats.value = h.queue_stats || {};
    lastChecked.value = new Date().toLocaleTimeString();
  } catch {
    services.value = [
      { name: 'Database', icon: '🗄️', status: 'unknown', details: {} },
      { name: 'Redis / Cache', icon: '⚡', status: 'unknown', details: {} },
      { name: 'Disk Storage', icon: '💾', status: 'unknown', details: {} },
      { name: 'Queue Workers', icon: '⚙️', status: 'unknown', details: {} },
    ];
  } finally {
    isLoading.value = false;
  }
}
</script>
