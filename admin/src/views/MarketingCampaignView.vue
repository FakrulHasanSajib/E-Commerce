<template>
  <div class="space-y-6">
    <div class="bg-slate-900 p-6 rounded-2xl border border-slate-800 flex items-center justify-between flex-wrap gap-4">
      <div>
        <h2 class="text-xl font-black text-white">Marketing Campaign Builder</h2>
        <p class="text-slate-400 text-xs mt-1">Create and schedule email/SMS/push campaigns with recipient targeting.</p>
      </div>
      <button @click="openForm()" class="px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white font-black text-xs rounded-xl shadow-lg">
        + Create Campaign
      </button>
    </div>

    <!-- Campaign List -->
    <div class="bg-slate-900 rounded-2xl border border-slate-800 overflow-x-auto">
      <table class="w-full text-left text-xs border-collapse">
        <thead>
          <tr class="border-b border-slate-800 text-slate-400 font-mono uppercase">
            <th class="p-4">Campaign Name</th>
            <th class="p-4">Type</th>
            <th class="p-4">Segment</th>
            <th class="p-4">Scheduled</th>
            <th class="p-4">Status</th>
            <th class="p-4 text-right">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-800 text-slate-200">
          <tr v-for="c in campaigns" :key="c.id" class="hover:bg-slate-800/50">
            <td class="p-4 font-bold text-white">{{ c.name }}</td>
            <td class="p-4">
              <span :class="['px-2 py-0.5 rounded text-[10px] font-bold uppercase', c.type === 'email' ? 'bg-blue-500/20 text-blue-300' : c.type === 'sms' ? 'bg-green-500/20 text-green-300' : 'bg-purple-500/20 text-purple-300']">
                {{ c.type }}
              </span>
            </td>
            <td class="p-4 font-mono text-indigo-300 text-[11px]">{{ c.target_segment || 'All' }}</td>
            <td class="p-4 font-mono text-slate-400 text-[11px]">{{ c.scheduled_at?.slice(0, 16) || 'Immediate' }}</td>
            <td class="p-4">
              <span :class="['px-2 py-0.5 rounded-full text-[10px] font-bold uppercase', c.status === 'sent' ? 'bg-emerald-500/20 text-emerald-300' : c.status === 'scheduled' ? 'bg-amber-500/20 text-amber-300' : 'bg-slate-700 text-slate-400']">
                {{ c.status || 'draft' }}
              </span>
            </td>
            <td class="p-4 text-right space-x-3">
              <button @click="openForm(c)" class="text-indigo-400 font-bold hover:underline">Edit</button>
              <button @click="deleteCampaign(c.id)" class="text-rose-400 font-bold hover:underline">Delete</button>
            </td>
          </tr>
        </tbody>
      </table>
      <div v-if="!campaigns.length" class="text-center py-12 text-slate-500 text-xs">No campaigns yet.</div>
    </div>

    <!-- Create/Edit Campaign Modal -->
    <div v-if="form.open" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/80 p-4">
      <div class="bg-slate-900 border border-slate-800 rounded-2xl p-6 max-w-lg w-full space-y-5 shadow-2xl overflow-y-auto max-h-[90vh]">
        <div class="flex items-center justify-between">
          <h3 class="font-bold text-white text-base">{{ form.id ? 'Edit Campaign' : 'New Campaign' }}</h3>
          <button @click="form.open = false" class="text-slate-400 hover:text-white font-bold text-xl">✕</button>
        </div>

        <div class="space-y-4">
          <div>
            <label class="block text-[10px] font-mono uppercase text-slate-400 mb-1.5">Campaign Name</label>
            <input v-model="form.name" type="text" placeholder="e.g. Eid Sale Newsletter"
              class="w-full px-4 py-2.5 bg-slate-950 border border-slate-800 text-white text-xs rounded-xl focus:outline-none focus:border-indigo-500" />
          </div>

          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-[10px] font-mono uppercase text-slate-400 mb-1.5">Campaign Type</label>
              <select v-model="form.type"
                class="w-full px-4 py-2.5 bg-slate-950 border border-slate-800 text-white text-xs rounded-xl focus:outline-none focus:border-indigo-500">
                <option value="email">📧 Email</option>
                <option value="sms">📱 SMS</option>
                <option value="push">🔔 Push Notification</option>
              </select>
            </div>
            <div>
              <label class="block text-[10px] font-mono uppercase text-slate-400 mb-1.5">Target Segment</label>
              <select v-model="form.target_segment"
                class="w-full px-4 py-2.5 bg-slate-950 border border-slate-800 text-white text-xs rounded-xl focus:outline-none focus:border-indigo-500">
                <option value="">All Customers</option>
                <option v-for="seg in segments" :key="seg.id" :value="seg.name">{{ seg.name }}</option>
              </select>
            </div>
          </div>

          <div>
            <label class="block text-[10px] font-mono uppercase text-slate-400 mb-1.5">Subject / Title</label>
            <input v-model="form.subject" type="text" placeholder="Campaign subject line"
              class="w-full px-4 py-2.5 bg-slate-950 border border-slate-800 text-white text-xs rounded-xl focus:outline-none focus:border-indigo-500" />
          </div>

          <div>
            <label class="block text-[10px] font-mono uppercase text-slate-400 mb-1.5">Message / Body</label>
            <textarea v-model="form.content" rows="5" placeholder="Write your campaign message here..."
              class="w-full px-4 py-2.5 bg-slate-950 border border-slate-800 text-white text-xs rounded-xl focus:outline-none focus:border-indigo-500 resize-none"></textarea>
          </div>

          <div>
            <label class="block text-[10px] font-mono uppercase text-slate-400 mb-1.5">Schedule Date/Time (leave blank for immediate)</label>
            <input v-model="form.scheduled_at" type="datetime-local"
              class="w-full px-4 py-2.5 bg-slate-950 border border-slate-800 text-white text-xs rounded-xl focus:outline-none focus:border-indigo-500" />
          </div>
        </div>

        <div class="flex gap-3 justify-end pt-2 border-t border-slate-800">
          <button @click="form.open = false" class="px-5 py-2.5 text-xs font-bold text-slate-400 hover:text-white">Cancel</button>
          <button @click="saveCampaign" :disabled="isSaving"
            class="px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white font-black text-xs rounded-xl disabled:opacity-50">
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

const campaigns = ref<any[]>([]);
const segments = ref<any[]>([]);
const isSaving = ref(false);

const defaultForm = () => ({
  open: false, id: null as any,
  name: '', type: 'email', target_segment: '',
  subject: '', content: '', scheduled_at: '',
});
const form = ref(defaultForm());

onMounted(() => { fetchAll(); });

async function fetchAll() {
  try { const r = await api.get('/admin/marketing-campaigns'); campaigns.value = r.data?.data || []; } catch {}
  try { const r = await api.get('/admin/customer-segments'); segments.value = r.data?.data || []; } catch {}
}

function openForm(c?: any) {
  form.value = {
    open: true,
    id: c?.id || null,
    name: c?.name || '',
    type: c?.type || 'email',
    target_segment: c?.target_segment || '',
    subject: c?.subject || '',
    content: c?.content || '',
    scheduled_at: c?.scheduled_at?.slice(0, 16) || '',
  };
}

async function saveCampaign() {
  isSaving.value = true;
  try {
    const payload = { name: form.value.name, type: form.value.type, target_segment: form.value.target_segment, subject: form.value.subject, content: form.value.content, scheduled_at: form.value.scheduled_at || null };
    if (form.value.id) { await api.put(`/admin/marketing-campaigns/${form.value.id}`, payload); }
    else { await api.post('/admin/marketing-campaigns', payload); }
    form.value.open = false;
    await fetchAll();
  } catch (e: any) {
    alert(e.response?.data?.message || 'Failed to save campaign.');
  } finally { isSaving.value = false; }
}

async function deleteCampaign(id: number) {
  if (confirm('Delete this campaign?')) {
    try { await api.delete(`/admin/marketing-campaigns/${id}`); await fetchAll(); } catch {}
  }
}
</script>
