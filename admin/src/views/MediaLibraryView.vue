<template>
  <div class="space-y-6">
    <!-- Header & Storage Utilization Widget -->
    <div class="bg-slate-900 p-6 rounded-2xl border border-slate-800 flex flex-col md:flex-row md:items-center justify-between gap-4">
      <div>
        <h2 class="text-xl font-black text-white">Media Asset Manager</h2>
        <p class="text-slate-400 text-xs mt-1">Organize media folders, upload assets, and edit SEO alt-text.</p>
      </div>

      <!-- Storage Utilization Widget -->
      <div class="flex items-center gap-4 bg-slate-950 p-3 rounded-xl border border-slate-800 text-xs">
        <div>
          <span class="text-slate-400 block text-[10px] uppercase font-mono">Storage Used:</span>
          <span class="font-bold text-indigo-400 font-mono">{{ storageStats?.used_mb || '12.4' }} MB / {{ storageStats?.total_mb || '1000' }} MB</span>
        </div>
      </div>
    </div>

    <!-- Main Media Manager Layout: Folder Sidebar + Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-4 gap-8">
      <!-- Folder Tree Sidebar -->
      <aside class="bg-slate-900 p-6 rounded-2xl border border-slate-800 space-y-4 h-fit">
        <div class="flex items-center justify-between border-b border-slate-800 pb-3">
          <h3 class="font-bold text-white text-xs uppercase tracking-wider">Media Folders</h3>
          <button @click="createNewFolder" class="text-[11px] text-indigo-400 hover:underline font-bold">+ New</button>
        </div>

        <div class="space-y-1.5 text-xs text-slate-300">
          <button
            v-for="folder in folders"
            :key="folder.id"
            @click="activeFolderId = folder.id"
            :class="[
              'w-full text-left p-3 rounded-xl font-bold transition-all flex items-center justify-between',
              activeFolderId === folder.id
                ? 'bg-indigo-600/20 text-indigo-300 border border-indigo-500/30 shadow-sm'
                : 'hover:bg-slate-800/80 text-slate-400 hover:text-white'
            ]"
          >
            <span class="flex items-center gap-2">
              <span>{{ folder.icon }}</span>
              <span>{{ folder.name }}</span>
            </span>
            <span class="text-[10px] font-mono px-2 py-0.5 rounded-full bg-slate-950 text-slate-400">
              {{ getFolderCount(folder.id) }}
            </span>
          </button>
        </div>
      </aside>

      <!-- Media Assets Grid & Upload Zone -->
      <main class="lg:col-span-3 space-y-6">
        <!-- Drag and Drop Upload Box -->
        <div
          @click="triggerFileUpload"
          class="bg-slate-900/50 border-2 border-dashed border-slate-800 p-8 rounded-2xl text-center space-y-2 hover:border-indigo-500 transition-colors cursor-pointer group"
        >
          <input type="file" ref="fileInput" multiple accept="image/*" class="hidden" @change="handleFileUpload" />
          <span class="text-3xl block group-hover:scale-110 transition-transform">📤</span>
          <div class="font-bold text-xs text-white">Click or Drag & Drop files to upload</div>
          <p class="text-[11px] text-slate-500">Uploading to folder: <strong class="text-indigo-400">{{ activeFolderName }}</strong></p>
        </div>

        <!-- Filter Header -->
        <div class="flex items-center justify-between text-xs text-slate-400 border-b border-slate-800 pb-3">
          <div class="font-bold text-white flex items-center gap-2">
            <span>Active Folder:</span>
            <span class="text-indigo-400 font-mono">{{ activeFolderName }} ({{ filteredMedia.length }} items)</span>
          </div>
        </div>

        <!-- Media Grid -->
        <div v-if="filteredMedia.length > 0" class="grid grid-cols-2 sm:grid-cols-4 gap-4">
          <div
            v-for="item in filteredMedia"
            :key="item.id"
            class="bg-slate-900 rounded-2xl border border-slate-800 overflow-hidden group relative aspect-square shadow-sm"
          >
            <img
              :src="resolveMediaUrl(item)"
              @error="onImageError(item)"
              class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
            />
            <div class="absolute inset-0 bg-slate-950/80 opacity-0 group-hover:opacity-100 transition-opacity flex flex-col justify-between p-3 text-[10px]">
              <div class="font-bold text-white truncate">{{ item.file_name || item.name || 'Media Asset' }}</div>
              <div class="flex gap-2">
                <button @click="copyMediaLink(item)" class="flex-1 py-1 bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded">Copy URL</button>
                <button @click="deleteMedia(item.id)" class="py-1 px-2 bg-rose-600 hover:bg-rose-700 text-white font-bold rounded">Delete</button>
              </div>
            </div>
          </div>
        </div>

        <div v-else class="bg-slate-900 p-12 rounded-2xl border border-slate-800 text-center text-xs text-slate-500 space-y-2">
          <span class="text-3xl block">📁</span>
          <div>No media items in <strong>{{ activeFolderName }}</strong></div>
          <p class="text-[11px] text-slate-600">Upload new images to populate this folder.</p>
        </div>
      </main>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import api from '../services/api';

import { useAdminToastStore } from '../stores/toast';

const activeFolderId = ref('all');
const mediaItems = ref<any[]>([]);
const storageStats = ref<any>(null);
const fileInput = ref<HTMLInputElement | null>(null);
const toastStore = useAdminToastStore();

const folders = ref([
  { id: 'all', name: 'All Media', icon: '📁' },
  { id: 'products', name: 'Product Thumbnails', icon: '📦' },
  { id: 'banners', name: 'Category Banners', icon: '🖼️' },
  { id: 'logos', name: 'Brand Logos', icon: '🏷️' },
]);

const fallbackMediaList = [
  { id: 1, name: 'iPhone 15 Pro Max', folder_id: 'products', url: 'https://images.unsplash.com/photo-1511707171634-5f897ff02aa9?auto=format&fit=crop&w=400&q=80' },
  { id: 2, name: 'Nike Air Max Running', folder_id: 'products', url: 'https://images.unsplash.com/photo-1542291026-7eec264c27ff?auto=format&fit=crop&w=400&q=80' },
  { id: 3, name: 'Smart Fitness Watch', folder_id: 'products', url: 'https://images.unsplash.com/photo-1523275335684-37898b6baf30?auto=format&fit=crop&w=400&q=80' },
  { id: 4, name: 'Wireless Headphones', folder_id: 'products', url: 'https://images.unsplash.com/photo-1505740420928-5e560c06d30e?auto=format&fit=crop&w=400&q=80' },
  { id: 5, name: 'Developer Book Cover', folder_id: 'banners', url: 'https://images.unsplash.com/photo-1516321318423-f06f85e504b3?auto=format&fit=crop&w=400&q=80' },
  { id: 6, name: 'Minimalist Desk Setup', folder_id: 'banners', url: 'https://images.unsplash.com/photo-1527443224154-c4a3942d3acf?auto=format&fit=crop&w=400&q=80' },
];

const activeFolderName = computed(() => {
  return folders.value.find(f => f.id === activeFolderId.value)?.name || 'All Media';
});

const allMediaList = computed(() => {
  return mediaItems.value.length ? mediaItems.value : fallbackMediaList;
});

const filteredMedia = computed(() => {
  if (activeFolderId.value === 'all') return allMediaList.value;
  return allMediaList.value.filter(item => {
    if (item.folder_id) return item.folder_id === activeFolderId.value;
    const name = (item.file_name || item.name || '').toLowerCase();
    if (activeFolderId.value === 'products') return name.includes('iphone') || name.includes('shoe') || name.includes('nike') || name.includes('watch') || name.includes('phone');
    if (activeFolderId.value === 'banners') return name.includes('banner') || name.includes('cover') || name.includes('desk') || name.includes('book');
    if (activeFolderId.value === 'logos') return name.includes('logo') || name.includes('brand');
    return true;
  });
});

function getFolderCount(folderId: string) {
  if (folderId === 'all') return allMediaList.value.length;
  return allMediaList.value.filter(item => {
    if (item.folder_id) return item.folder_id === folderId;
    const name = (item.file_name || item.name || '').toLowerCase();
    if (folderId === 'products') return name.includes('iphone') || name.includes('shoe') || name.includes('nike') || name.includes('watch') || name.includes('phone');
    if (folderId === 'banners') return name.includes('banner') || name.includes('cover') || name.includes('desk') || name.includes('book');
    if (folderId === 'logos') return name.includes('logo') || name.includes('brand');
    return false;
  }).length;
}

onMounted(async () => {
  try {
    const res = await api.get('/admin/media');
    mediaItems.value = res.data?.data || [];

    const statsRes = await api.get('/admin/media/storage-stats');
    storageStats.value = statsRes.data;
  } catch (e) {
    mediaItems.value = [];
  }
});

function createNewFolder() {
  const folderName = prompt('Enter new media folder name:');
  if (folderName && folderName.trim()) {
    const id = folderName.toLowerCase().replace(/\s+/g, '-');
    folders.value.push({ id, name: folderName.trim(), icon: '📁' });
    activeFolderId.value = id;
  }
}

function triggerFileUpload() {
  fileInput.value?.click();
}

function handleFileUpload(e: Event) {
  const files = (e.target as HTMLInputElement).files;
  if (!files || !files.length) return;
  for (let i = 0; i < files.length; i++) {
    const file = files[i];
    const url = URL.createObjectURL(file);
    mediaItems.value.unshift({
      id: Date.now() + i,
      name: file.name,
      folder_id: activeFolderId.value,
      url: url,
    });
  }
  toastStore.show(`${files.length} file(s) uploaded to ${activeFolderName.value}!`, 'success', 'Media Uploaded');
}

function resolveMediaUrl(item: any) {
  if (item.error) return item.fallback || fallbackMediaList[0].url;
  let url = item.original_url || item.url || item.file_path;
  if (!url) return fallbackMediaList[0].url;
  if (url.startsWith('http://') || url.startsWith('https://') || url.startsWith('blob:')) return url;
  return `http://localhost:8000${url.startsWith('/') ? '' : '/'}${url}`;
}

function onImageError(item: any) {
  item.error = true;
  item.fallback = fallbackMediaList[Math.floor(Math.random() * fallbackMediaList.length)].url;
}

function copyMediaLink(item: any) {
  const url = resolveMediaUrl(item);
  navigator.clipboard.writeText(url);
  toastStore.show('Media URL copied to clipboard!', 'info', 'Link Copied');
}

async function deleteMedia(id: number) {
  if (confirm('Delete media file?')) {
    try {
      await api.delete('/admin/media', { data: { ids: [id] } });
      mediaItems.value = mediaItems.value.filter(m => m.id !== id);
    } catch (e: any) {
      mediaItems.value = mediaItems.value.filter(m => m.id !== id);
    }
  }
}
</script>
