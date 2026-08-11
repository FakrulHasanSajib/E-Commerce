<template>
  <div v-if="isOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/80 p-6">
    <div class="bg-slate-900 border border-slate-800 rounded-3xl max-w-4xl w-full h-[80vh] flex flex-col shadow-2xl overflow-hidden">
      <!-- Header -->
      <div class="p-6 border-b border-slate-800 flex items-center justify-between">
        <h3 class="font-bold text-white text-base">Select Media Asset</h3>
        <button @click="close" class="text-slate-400 hover:text-white font-bold text-sm">✕</button>
      </div>

      <!-- Media Grid -->
      <div class="flex-1 p-6 overflow-y-auto grid grid-cols-3 sm:grid-cols-4 md:grid-cols-6 gap-4">
        <div
          v-for="item in mediaList"
          :key="item.id"
          @click="selectMedia(item)"
          class="aspect-square bg-slate-950 rounded-xl border border-slate-800 overflow-hidden cursor-pointer hover:border-indigo-500 transition-all relative group"
        >
          <img :src="item.original_url || item.url" class="w-full h-full object-cover" />
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue';
import api from '../services/api';

const props = defineProps<{ isOpen: boolean }>();
const emit = defineEmits(['close', 'select']);

const mediaList = ref<any[]>([]);

onMounted(async () => {
  try {
    const res = await api.get('/admin/media');
    mediaList.value = res.data?.data || [];
  } catch (e) {
    mediaList.value = [];
  }
});

function close() {
  emit('close');
}

function selectMedia(item: any) {
  emit('select', item);
  close();
}
</script>
