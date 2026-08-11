<template>
  <div class="space-y-6">
    <div class="bg-slate-900 p-6 rounded-2xl border border-slate-800">
      <h2 class="text-xl font-black text-white">Roles & Permissions (RBAC)</h2>
      <p class="text-slate-400 text-xs mt-1">Manage system roles, assign permissions, and control user access levels.</p>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
      <!-- ROLES PANEL -->
      <div class="bg-slate-900 p-6 rounded-2xl border border-slate-800 space-y-4">
        <div class="flex items-center justify-between border-b border-slate-800 pb-3">
          <h3 class="font-bold text-white text-sm">System Roles</h3>
          <button @click="createRole" class="px-3 py-1.5 bg-indigo-600 text-white font-bold text-[10px] rounded-lg">+ Add Role</button>
        </div>
        <div class="space-y-2">
          <div
            v-for="role in roles" :key="role.id"
            @click="selectedRole = role"
            :class="['p-3 rounded-xl border text-xs cursor-pointer transition-all', selectedRole?.id === role.id ? 'bg-indigo-600/20 border-indigo-500/40 text-white' : 'bg-slate-950 border-slate-800 text-slate-300 hover:border-indigo-500/30']"
          >
            <div class="font-bold">{{ role.name }}</div>
            <div class="text-slate-400 text-[10px]">{{ role.permissions?.length || 0 }} permissions</div>
          </div>
        </div>
      </div>

      <!-- PERMISSIONS PANEL -->
      <div class="bg-slate-900 p-6 rounded-2xl border border-slate-800 space-y-4">
        <h3 class="font-bold text-white text-sm border-b border-slate-800 pb-3">
          All Permissions
          <span v-if="selectedRole" class="text-indigo-400 font-mono text-[10px] ml-2">(Viewing: {{ selectedRole.name }})</span>
        </h3>
        <div class="grid grid-cols-2 gap-2 max-h-72 overflow-y-auto pr-1">
          <div v-for="perm in permissions" :key="perm.id" class="flex items-center gap-2 p-2 bg-slate-950 rounded-lg border border-slate-800 text-[10px]">
            <span class="font-mono text-slate-300">{{ perm.name }}</span>
          </div>
        </div>
      </div>
    </div>

    <!-- ASSIGN ROLE TO USER -->
    <div class="bg-slate-900 p-6 rounded-2xl border border-slate-800 space-y-4">
      <h3 class="font-bold text-white text-sm border-b border-slate-800 pb-3">Assign / Sync User Roles & Permissions</h3>
      <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-xs">
        <div>
          <label class="block font-mono text-slate-400 mb-1 text-[10px] uppercase">User ID</label>
          <input v-model.number="assignForm.userId" type="number" placeholder="User ID" class="w-full px-3 py-2 bg-slate-950 border border-slate-800 text-white text-xs rounded-xl" />
        </div>
        <div>
          <label class="block font-mono text-slate-400 mb-1 text-[10px] uppercase">Role Name</label>
          <select v-model="assignForm.role" class="w-full px-3 py-2 bg-slate-950 border border-slate-800 text-white text-xs rounded-xl">
            <option v-for="r in roles" :key="r.id" :value="r.name">{{ r.name }}</option>
          </select>
        </div>
        <div class="flex items-end gap-2">
          <button @click="syncUserRole" class="px-4 py-2.5 bg-indigo-600 text-white font-bold text-xs rounded-xl flex-1">Sync Role</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue';
import api from '../services/api';

const roles = ref<any[]>([]);
const permissions = ref<any[]>([]);
const selectedRole = ref<any>(null);
const assignForm = ref({ userId: null as number | null, role: '' });

onMounted(() => { fetchAll(); });

async function fetchAll() {
  try { const r = await api.get('/admin/roles'); roles.value = r.data?.data || []; } catch {}
  try { const r = await api.get('/admin/permissions'); permissions.value = r.data?.data || []; } catch {}
}
async function createRole() {
  const name = prompt('New role name:');
  if (name) { await api.post('/admin/roles', { name }); fetchAll(); }
}
async function syncUserRole() {
  if (!assignForm.value.userId || !assignForm.value.role) return alert('Fill in User ID and Role.');
  await api.patch(`/admin/users/${assignForm.value.userId}/roles`, { roles: [assignForm.value.role] });
  alert(`Role [${assignForm.value.role}] synced to user #${assignForm.value.userId}!`);
}
</script>
