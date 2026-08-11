<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="bg-slate-900 p-6 rounded-2xl border border-slate-800 flex items-center justify-between flex-wrap gap-4">
      <div>
        <h2 class="text-xl font-black text-white">🎨 Landing Page Builder</h2>
        <p class="text-slate-400 text-xs mt-1">Drag-and-drop section builder, canvas preview, version history, and device visibility controls.</p>
      </div>
      <div class="flex gap-2">
        <button @click="createNewPage" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs rounded-xl shadow-md transition-all">
          + New Landing Page
        </button>
      </div>
    </div>

    <!-- Page Selector & Controls Bar -->
    <div v-if="pages.length" class="bg-slate-900 p-4 rounded-2xl border border-slate-800 flex items-center justify-between flex-wrap gap-4 text-xs">
      <div class="flex items-center gap-3">
        <span class="text-slate-400 font-bold">Select Page:</span>
        <select v-model="selectedPageId" @change="loadPageDetails" class="px-3 py-2 bg-slate-950 border border-slate-800 text-white font-bold rounded-xl">
          <option v-for="p in pages" :key="p.id" :value="p.id">{{ p.title }} ({{ p.slug }})</option>
        </select>
        <span :class="['px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase', selectedPage?.is_published ? 'bg-emerald-500/20 text-emerald-300' : 'bg-amber-500/20 text-amber-300']">
          {{ selectedPage?.is_published ? 'Published' : 'Draft' }}
        </span>
      </div>

      <div class="flex gap-2">
        <button @click="previewDevice = 'desktop'" :class="['px-3 py-1.5 rounded-lg font-mono text-[11px]', previewDevice === 'desktop' ? 'bg-indigo-600 text-white' : 'bg-slate-950 text-slate-400']">🖥 Desktop</button>
        <button @click="previewDevice = 'mobile'" :class="['px-3 py-1.5 rounded-lg font-mono text-[11px]', previewDevice === 'mobile' ? 'bg-indigo-600 text-white' : 'bg-slate-950 text-slate-400']">📱 Mobile</button>
        <button @click="savePage" :disabled="isSaving" class="px-4 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white font-bold rounded-xl transition-all disabled:opacity-50">
          {{ isSaving ? 'Saving...' : '💾 Save Page' }}
        </button>
        <button @click="publishPage" class="px-4 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-xl transition-all">
          🚀 Publish Page
        </button>
      </div>
    </div>

    <!-- Builder Workspace (Palette + Canvas + Section Settings) -->
    <div v-if="selectedPage" class="grid grid-cols-1 lg:grid-cols-4 gap-6">
      <!-- 1. Section Palette (Left Panel) -->
      <div class="bg-slate-900 p-4 rounded-2xl border border-slate-800 space-y-4">
        <h3 class="font-bold text-white text-xs uppercase tracking-widest border-b border-slate-800 pb-2">Section Palette</h3>
        <p class="text-slate-400 text-[11px]">Click or drag a section block to add it to the page canvas.</p>

        <div class="space-y-2">
          <button v-for="st in availableSectionTypes" :key="st.type" @click="addSection(st.type)"
            class="w-full p-3 bg-slate-950 hover:bg-indigo-950/50 border border-slate-800 hover:border-indigo-500/50 rounded-xl text-left transition-all group">
            <div class="flex items-center gap-2 font-bold text-white text-xs group-hover:text-indigo-300">
              <span>{{ st.icon }}</span>
              <span>{{ st.label }}</span>
            </div>
            <div class="text-[10px] text-slate-500 mt-0.5">{{ st.description }}</div>
          </button>
        </div>
      </div>

      <!-- 2. Interactive Canvas (Middle) -->
      <div class="lg:col-span-2 bg-slate-900 p-4 rounded-2xl border border-slate-800 space-y-4">
        <div class="flex items-center justify-between border-b border-slate-800 pb-2">
          <h3 class="font-bold text-white text-xs uppercase tracking-widest">Page Canvas ({{ canvasSections.length }} sections)</h3>
          <span class="text-slate-500 text-[10px] font-mono">Drag ↑↓ to reorder</span>
        </div>

        <div :class="['mx-auto transition-all duration-300 space-y-3 min-h-[400px]', previewDevice === 'mobile' ? 'max-w-xs' : 'w-full']">
          <div v-for="(sec, idx) in canvasSections" :key="sec.id || idx"
            @click="activeSectionIndex = idx"
            :class="['p-4 rounded-xl border transition-all cursor-pointer relative group', activeSectionIndex === idx ? 'bg-indigo-950/40 border-indigo-500' : 'bg-slate-950 border-slate-800 hover:border-slate-700']">
            <div class="flex items-center justify-between mb-2">
              <div class="flex items-center gap-2 text-xs font-bold text-indigo-400">
                <span>≡</span>
                <span class="capitalize">{{ sec.type?.replace('_', ' ') }} Section</span>
              </div>
              <div class="flex items-center gap-1.5">
                <button @click.stop="moveSection(idx, -1)" :disabled="idx === 0" class="text-slate-400 hover:text-white px-1 disabled:opacity-30">↑</button>
                <button @click.stop="moveSection(idx, 1)" :disabled="idx === canvasSections.length - 1" class="text-slate-400 hover:text-white px-1 disabled:opacity-30">↓</button>
                <button @click.stop="deleteSection(idx)" class="text-rose-400 hover:text-rose-300 font-bold px-1">✕</button>
              </div>
            </div>

            <!-- Canvas Section Preview -->
            <div class="bg-slate-900/80 p-3 rounded-lg border border-slate-800 text-[11px] text-slate-300 space-y-1">
              <div class="font-bold text-white">{{ sec.settings?.title || sec.type }}</div>
              <div class="text-slate-400 text-[10px] truncate">{{ sec.settings?.subtitle || sec.settings?.content || 'Click to edit properties →' }}</div>
            </div>
          </div>

          <div v-if="!canvasSections.length" class="text-center py-16 text-slate-600 text-xs border border-dashed border-slate-800 rounded-xl space-y-2">
            <div class="text-3xl">🎨</div>
            <div>Canvas is empty. Click any section from the palette to start building!</div>
          </div>
        </div>
      </div>

      <!-- 3. Section Inspector / Settings Panel (Right) -->
      <div class="bg-slate-900 p-4 rounded-2xl border border-slate-800 space-y-4">
        <h3 class="font-bold text-white text-xs uppercase tracking-widest border-b border-slate-800 pb-2">Section Settings</h3>

        <div v-if="activeSection" class="space-y-4 text-xs">
          <div class="p-2 bg-indigo-950/40 border border-indigo-800/40 rounded-xl text-indigo-300 font-bold capitalize">
            Editing: {{ activeSection.type?.replace('_', ' ') }}
          </div>

          <div>
            <label class="block text-slate-400 text-[10px] uppercase font-mono mb-1">Section Title</label>
            <input v-model="activeSection.settings.title" type="text"
              class="w-full px-3 py-2 bg-slate-950 border border-slate-800 text-white rounded-xl focus:outline-none focus:border-indigo-500" />
          </div>

          <div>
            <label class="block text-slate-400 text-[10px] uppercase font-mono mb-1">Subtitle / Caption</label>
            <input v-model="activeSection.settings.subtitle" type="text"
              class="w-full px-3 py-2 bg-slate-950 border border-slate-800 text-white rounded-xl focus:outline-none focus:border-indigo-500" />
          </div>

          <div>
            <label class="block text-slate-400 text-[10px] uppercase font-mono mb-1">Device Visibility</label>
            <select v-model="activeSection.device_visibility" class="w-full px-3 py-2 bg-slate-950 border border-slate-800 text-white rounded-xl">
              <option value="all">All Devices</option>
              <option value="desktop_only">Desktop Only</option>
              <option value="mobile_only">Mobile Only</option>
            </select>
          </div>

          <div class="flex items-center gap-2 pt-2">
            <input type="checkbox" v-model="activeSection.is_visible" id="vis_check" class="text-indigo-600 rounded" />
            <label for="vis_check" class="text-slate-300 font-bold">Section Visible on Live Site</label>
          </div>
        </div>

        <div v-else class="text-center py-12 text-slate-600 text-xs">
          Select a section on the canvas to configure its settings.
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import api from '../services/api';

const pages = ref<any[]>([]);
const selectedPageId = ref<number | null>(null);
const selectedPage = ref<any>(null);
const canvasSections = ref<any[]>([]);
const activeSectionIndex = ref<number | null>(null);
const previewDevice = ref<'desktop' | 'mobile'>('desktop');
const isSaving = ref(false);

const availableSectionTypes = [
  { type: 'hero_slider', icon: '🖼️', label: 'Hero Banner Slider', description: 'Full-width banner carousel' },
  { type: 'featured_products', icon: '⭐', label: 'Featured Products Grid', description: 'Product grid with add to cart' },
  { type: 'flash_sale', icon: '⚡', label: 'Flash Sale Timer Block', description: 'Countdown timer with deal items' },
  { type: 'category_showcase', icon: '📁', label: 'Category Tiles', description: 'Visual category grid' },
  { type: 'testimonials', icon: '💬', label: 'Customer Testimonials', description: 'Reviews and feedback slider' },
  { type: 'blog_preview', icon: '📰', label: 'Latest Blog Posts', description: 'Recent articles preview' },
];

const activeSection = computed(() => {
  if (activeSectionIndex.value !== null && canvasSections.value[activeSectionIndex.value]) {
    return canvasSections.value[activeSectionIndex.value];
  }
  return null;
});

onMounted(() => { fetchPages(); });

async function fetchPages() {
  try {
    const res = await api.get('/admin/landing-pages');
    pages.value = res.data?.data || [];
    if (pages.value.length && !selectedPageId.value) {
      selectedPageId.value = pages.value[0].id;
      loadPageDetails();
    }
  } catch {}
}

async function loadPageDetails() {
  if (!selectedPageId.value) return;
  try {
    const res = await api.get(`/admin/landing-pages/${selectedPageId.value}`);
    selectedPage.value = res.data?.data || pages.value.find(p => p.id === selectedPageId.value);
    canvasSections.value = selectedPage.value?.sections || [];
    if (canvasSections.value.length) activeSectionIndex.value = 0;
  } catch {
    selectedPage.value = pages.value.find(p => p.id === selectedPageId.value);
    canvasSections.value = [];
  }
}

async function createNewPage() {
  const title = prompt('Enter New Landing Page Title:');
  if (title) {
    try {
      const res = await api.post('/admin/landing-pages', { title, slug: title.toLowerCase().replace(/\s+/g, '-') });
      await fetchPages();
      selectedPageId.value = res.data?.data?.id || pages.value[0]?.id;
      await loadPageDetails();
    } catch (e: any) { alert(e.response?.data?.message || 'Failed to create page.'); }
  }
}

function addSection(type: string) {
  canvasSections.value.push({
    id: Date.now(),
    type,
    device_visibility: 'all',
    is_visible: true,
    settings: { title: `New ${type.replace('_', ' ')}`, subtitle: '', content: '' },
  });
  activeSectionIndex.value = canvasSections.value.length - 1;
}

function moveSection(index: number, direction: number) {
  const target = index + direction;
  if (target < 0 || target >= canvasSections.value.length) return;
  const temp = canvasSections.value[index];
  canvasSections.value[index] = canvasSections.value[target];
  canvasSections.value[target] = temp;
  activeSectionIndex.value = target;
}

function deleteSection(index: number) {
  canvasSections.value.splice(index, 1);
  if (canvasSections.value.length) activeSectionIndex.value = 0;
  else activeSectionIndex.value = null;
}

async function savePage() {
  if (!selectedPageId.value) return;
  isSaving.value = true;
  try {
    await api.put(`/admin/landing-pages/${selectedPageId.value}`, {
      sections: canvasSections.value,
    });
    alert('Landing page layout saved!');
  } catch (e: any) {
    alert(e.response?.data?.message || 'Failed to save layout.');
  } finally {
    isSaving.value = false;
  }
}

async function publishPage() {
  if (!selectedPageId.value) return;
  try {
    await api.post(`/admin/landing-pages/${selectedPageId.value}/publish`);
    alert('Landing page published successfully!');
    await loadPageDetails();
  } catch (e: any) {
    alert(e.response?.data?.message || 'Failed to publish page.');
  }
}
</script>
