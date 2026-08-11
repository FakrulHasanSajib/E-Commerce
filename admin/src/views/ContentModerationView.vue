<template>
  <div class="space-y-6">
    <div class="bg-slate-900 p-6 rounded-2xl border border-slate-800 flex items-center justify-between">
      <div>
        <h2 class="text-xl font-black text-white">Content & Moderation Center</h2>
        <p class="text-slate-400 text-xs mt-1">Moderate reviews, manage blog articles, and publish landing pages.</p>
      </div>
      <div class="flex gap-2">
        <button v-for="tab in tabs" :key="tab.key" @click="activeTab = tab.key"
          :class="['px-3 py-2 rounded-xl text-xs font-bold uppercase transition-all', activeTab === tab.key ? 'bg-indigo-600 text-white' : 'bg-slate-950 text-slate-400 hover:text-white']">
          {{ tab.label }}
        </button>
      </div>
    </div>

    <!-- TAB 1: REVIEWS MODERATION -->
    <div v-if="activeTab === 'reviews'" class="bg-slate-900 p-6 rounded-2xl border border-slate-800 space-y-4">
      <h3 class="font-bold text-white text-sm border-b border-slate-800 pb-3">Product Reviews Moderation Queue</h3>
      <div class="space-y-3">
        <div v-for="r in reviews" :key="r.id" class="p-4 bg-slate-950 rounded-xl border border-slate-800 text-xs space-y-2">
          <div class="flex items-start justify-between gap-4">
            <div>
              <div class="font-bold text-white">{{ r.title }}</div>
              <div class="text-slate-400 mt-1">{{ r.body }}</div>
              <div class="text-slate-500 text-[10px] mt-1">Rating: {{ r.rating }}/5 • Product: {{ r.product?.name }}</div>
            </div>
            <div class="flex gap-2 shrink-0">
              <button @click="updateReviewStatus(r.id, 'approved')" class="px-2 py-1 bg-emerald-600 text-white text-[10px] font-bold rounded-lg">Approve</button>
              <button @click="updateReviewStatus(r.id, 'rejected')" class="px-2 py-1 bg-amber-600 text-white text-[10px] font-bold rounded-lg">Reject</button>
              <button @click="deleteReview(r.id)" class="px-2 py-1 bg-rose-600 text-white text-[10px] font-bold rounded-lg">Delete</button>
            </div>
          </div>
        </div>
        <div v-if="!reviews.length" class="text-center py-8 text-slate-500 text-xs">No reviews pending moderation.</div>
      </div>
    </div>

    <!-- TAB 2: BLOG ARTICLES -->
    <div v-if="activeTab === 'articles'" class="bg-slate-900 p-6 rounded-2xl border border-slate-800 space-y-4">
      <div class="flex items-center justify-between border-b border-slate-800 pb-3">
        <h3 class="font-bold text-white text-sm">Blog Article Manager</h3>
        <button @click="openArticleForm()" class="px-3 py-1.5 bg-indigo-600 text-white font-bold text-[10px] rounded-lg">+ New Article</button>
      </div>
      <div class="space-y-2">
        <div v-for="a in articles" :key="a.id" class="flex items-center justify-between p-3 bg-slate-950 rounded-xl border border-slate-800 text-xs">
          <div>
            <div class="font-bold text-white">{{ a.title }}</div>
            <div class="text-slate-400 font-mono text-[10px]">{{ a.slug }} • Published: {{ a.published_at || 'Draft' }}</div>
          </div>
          <div class="flex gap-2">
            <button @click="openArticleForm(a)" class="text-indigo-400 font-bold hover:underline">Edit</button>
            <button @click="deleteArticle(a.id)" class="text-rose-400 font-bold hover:underline">Delete</button>
          </div>
        </div>
      </div>
    </div>

    <!-- TAB 3: LANDING PAGES -->
    <div v-if="activeTab === 'landing'" class="bg-slate-900 p-6 rounded-2xl border border-slate-800 space-y-4">
      <div class="flex items-center justify-between border-b border-slate-800 pb-3">
        <h3 class="font-bold text-white text-sm">Landing Page Builder</h3>
        <button @click="createLandingPage" class="px-3 py-1.5 bg-indigo-600 text-white font-bold text-[10px] rounded-lg">+ New Page</button>
      </div>
      <div class="space-y-2">
        <div v-for="p in landingPages" :key="p.id" class="flex items-center justify-between p-3 bg-slate-950 rounded-xl border border-slate-800 text-xs">
          <div>
            <div class="font-bold text-white">{{ p.title }}</div>
            <div class="text-slate-400 font-mono text-[10px]">Slug: {{ p.slug }} • Sections: {{ p.sections?.length || 0 }}</div>
          </div>
          <div class="flex gap-2">
            <button @click="publishPage(p.id)" class="text-emerald-400 font-bold hover:underline">Publish</button>
            <button @click="deleteLandingPage(p.id)" class="text-rose-400 font-bold hover:underline">Delete</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Article Form Modal -->
    <div v-if="articleForm.open" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/80 p-4">
      <div class="bg-slate-900 border border-slate-800 rounded-2xl p-6 max-w-lg w-full space-y-4">
        <h3 class="font-bold text-white">{{ articleForm.id ? 'Edit Article' : 'New Article' }}</h3>
        <input v-model="articleForm.title" placeholder="Article title" class="w-full px-3 py-2 bg-slate-950 border border-slate-800 text-white text-xs rounded-xl" />
        <input v-model="articleForm.slug" placeholder="slug-here" class="w-full px-3 py-2 bg-slate-950 border border-slate-800 text-white text-xs rounded-xl font-mono" />
        <textarea v-model="articleForm.content" rows="4" placeholder="Article content..." class="w-full px-3 py-2 bg-slate-950 border border-slate-800 text-white text-xs rounded-xl"></textarea>
        <div class="flex gap-2 justify-end">
          <button @click="articleForm.open = false" class="px-4 py-2 text-xs font-bold text-slate-400">Cancel</button>
          <button @click="saveArticle" class="px-4 py-2 bg-indigo-600 text-white font-bold text-xs rounded-xl">Save Article</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue';
import api from '../services/api';

const tabs = [
  { key: 'reviews', label: 'Reviews' },
  { key: 'articles', label: 'Articles' },
  { key: 'landing', label: 'Landing Pages' },
];
const activeTab = ref('reviews');
const reviews = ref<any[]>([]);
const articles = ref<any[]>([]);
const landingPages = ref<any[]>([]);
const articleForm = ref({ open: false, id: null as any, title: '', slug: '', content: '' });

onMounted(() => { fetchAll(); });

async function fetchAll() {
  try { const r = await api.get('/admin/reviews'); reviews.value = r.data?.data || []; } catch {}
  try { const r = await api.get('/admin/articles'); articles.value = r.data?.data || []; } catch {}
  try { const r = await api.get('/admin/landing-pages'); landingPages.value = r.data?.data || []; } catch {}
}
async function updateReviewStatus(id: number, status: string) {
  await api.put(`/admin/reviews/${id}/status`, { status }); fetchAll();
}
async function deleteReview(id: number) {
  if (confirm('Delete review?')) { await api.delete(`/admin/reviews/${id}`); fetchAll(); }
}
function openArticleForm(article?: any) {
  articleForm.value = { open: true, id: article?.id || null, title: article?.title || '', slug: article?.slug || '', content: article?.content || '' };
}
async function saveArticle() {
  const payload = { title: articleForm.value.title, slug: articleForm.value.slug, content: articleForm.value.content };
  if (articleForm.value.id) { await api.put(`/admin/articles/${articleForm.value.id}`, payload); }
  else { await api.post('/admin/articles', payload); }
  articleForm.value.open = false; fetchAll();
}
async function deleteArticle(id: number) {
  if (confirm('Delete article?')) { await api.delete(`/admin/articles/${id}`); fetchAll(); }
}
async function createLandingPage() {
  const title = prompt('Landing page title:');
  if (title) { await api.post('/admin/landing-pages', { title, slug: title.toLowerCase().replace(/\s+/g, '-') }); fetchAll(); }
}
async function publishPage(id: number) {
  await api.post(`/admin/landing-pages/${id}/publish`); alert('Page published!'); fetchAll();
}
async function deleteLandingPage(id: number) {
  if (confirm('Delete landing page?')) { await api.delete(`/admin/landing-pages/${id}`); fetchAll(); }
}
</script>
