<template>
  <div class="space-y-6">
    <div class="bg-slate-900 p-6 rounded-2xl border border-slate-800 flex items-center justify-between">
      <div>
        <h2 class="text-xl font-black text-white">SEO & Redirects Manager</h2>
        <p class="text-slate-400 text-xs mt-1">Manage 301 redirects and monitor 404 error logs.</p>
      </div>
      <div class="flex gap-2">
        <button v-for="tab in tabs" :key="tab.key" @click="activeTab = tab.key"
          :class="['px-3 py-2 rounded-xl text-xs font-bold uppercase transition-all', activeTab === tab.key ? 'bg-indigo-600 text-white' : 'bg-slate-950 text-slate-400 hover:text-white']">
          {{ tab.label }}
        </button>
      </div>
    </div>

    <!-- TAB 1: REDIRECTS -->
    <div v-if="activeTab === 'redirects'" class="bg-slate-900 p-6 rounded-2xl border border-slate-800 space-y-4">
      <div class="flex items-center justify-between border-b border-slate-800 pb-3">
        <h3 class="font-bold text-white text-sm">301/302 Redirect Rules</h3>
        <button @click="showAddRedirect = true" class="px-3 py-1.5 bg-indigo-600 text-white font-bold text-[10px] rounded-lg">+ Add Redirect</button>
      </div>

      <div v-if="showAddRedirect" class="p-4 bg-slate-950 rounded-xl border border-indigo-500/30 space-y-3">
        <div class="grid grid-cols-3 gap-3">
          <input v-model="newRedirect.from_path" placeholder="/old-url" class="px-3 py-2 bg-slate-900 border border-slate-800 text-white text-xs rounded-xl font-mono" />
          <input v-model="newRedirect.to_path" placeholder="/new-url" class="px-3 py-2 bg-slate-900 border border-slate-800 text-white text-xs rounded-xl font-mono" />
          <select v-model="newRedirect.status_code" class="px-3 py-2 bg-slate-900 border border-slate-800 text-white text-xs rounded-xl">
            <option :value="301">301 Permanent</option>
            <option :value="302">302 Temporary</option>
          </select>
        </div>
        <div class="flex gap-2">
          <button @click="saveRedirect" class="px-4 py-2 bg-indigo-600 text-white text-xs font-bold rounded-xl">Save Redirect</button>
          <button @click="showAddRedirect = false" class="px-4 py-2 text-xs font-bold text-slate-400">Cancel</button>
        </div>
      </div>

      <div class="overflow-x-auto">
        <table class="w-full text-left text-xs border-collapse">
          <thead><tr class="border-b border-slate-800 text-slate-400 font-mono uppercase">
            <th class="p-4">From Path</th><th class="p-4">To Path</th><th class="p-4">Code</th><th class="p-4 text-right">Actions</th>
          </tr></thead>
          <tbody class="divide-y divide-slate-800 text-slate-200">
            <tr v-for="r in redirects" :key="r.id" class="hover:bg-slate-800/50">
              <td class="p-4 font-mono text-rose-400">{{ r.from_path }}</td>
              <td class="p-4 font-mono text-emerald-400">{{ r.to_path }}</td>
              <td class="p-4 font-bold">{{ r.status_code }}</td>
              <td class="p-4 text-right">
                <button @click="deleteRedirect(r.id)" class="text-rose-400 font-bold hover:underline">Delete</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- TAB 2: 404 LOGS -->
    <div v-if="activeTab === '404logs'" class="bg-slate-900 p-6 rounded-2xl border border-slate-800 space-y-4">
      <h3 class="font-bold text-white text-sm border-b border-slate-800 pb-3">404 Not Found Error Log</h3>
      <div class="space-y-2">
        <div v-for="log in notFoundLogs" :key="log.id" class="flex items-center justify-between p-3 bg-slate-950 rounded-xl border border-slate-800 text-xs">
          <div>
            <div class="font-mono text-rose-400 font-bold">{{ log.url }}</div>
            <div class="text-slate-500 text-[10px] mt-0.5">IP: {{ log.ip_address }} • {{ log.user_agent?.slice(0, 60) }}...</div>
          </div>
          <div class="text-slate-400 font-mono text-[10px] shrink-0">{{ log.created_at }}</div>
        </div>
        <div v-if="!notFoundLogs.length" class="text-center py-8 text-slate-500 text-xs">No 404 errors logged yet.</div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue';
import api from '../services/api';

const tabs = [{ key: 'redirects', label: 'Redirects' }, { key: '404logs', label: '404 Logs' }];
const activeTab = ref('redirects');
const redirects = ref<any[]>([]);
const notFoundLogs = ref<any[]>([]);
const showAddRedirect = ref(false);
const newRedirect = ref({ from_path: '', to_path: '', status_code: 301 });

onMounted(() => { fetchRedirects(); fetchLogs(); });

async function fetchRedirects() {
  try { const r = await api.get('/admin/redirects'); redirects.value = r.data?.data || []; } catch {}
}
async function fetchLogs() {
  try { const r = await api.get('/admin/404-logs'); notFoundLogs.value = r.data?.data || []; } catch {}
}
async function saveRedirect() {
  await api.post('/admin/redirects', newRedirect.value);
  newRedirect.value = { from_path: '', to_path: '', status_code: 301 };
  showAddRedirect.value = false;
  fetchRedirects();
}
async function deleteRedirect(id: number) {
  if (confirm('Delete redirect rule?')) { await api.delete(`/admin/redirects/${id}`); fetchRedirects(); }
}
</script>
