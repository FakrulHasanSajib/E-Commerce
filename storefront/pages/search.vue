<template>
  <div class="space-y-6">
    <!-- Search Bar & Results Summary -->
    <div class="bg-white p-6 rounded-2xl border border-slate-100 shadow-xs space-y-4">
      <div class="relative max-w-2xl">
        <input
          v-model="searchQuery"
          type="text"
          placeholder="Search products..."
          class="w-full pl-10 pr-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-hidden focus:border-indigo-500 focus:bg-white transition-all font-medium text-slate-900"
          @input="handleInput"
        />
        <span class="absolute left-3.5 top-3.5 text-slate-400">🔍</span>
      </div>

      <div class="flex items-center justify-between text-xs text-slate-500 font-medium">
        <span>Showing search results for: <strong class="text-slate-900 font-bold">"{{ searchQuery }}"</strong></span>
        <span>Total {{ meta.total || 0 }} items</span>
      </div>
    </div>

    <!-- Smart "No Results Found" Empty State -->
    <div v-if="!pending && (!products || products.length === 0)" class="bg-white p-12 rounded-2xl border border-slate-100 text-center space-y-6">
      <div class="w-16 h-16 bg-slate-100 rounded-full flex items-center justify-center text-3xl mx-auto">
        🧐
      </div>
      <div>
        <h2 class="text-xl font-black text-slate-900">No results found for "{{ searchQuery }}"</h2>
        <p class="text-slate-500 text-xs mt-1">We couldn't find any products matching your search term.</p>
      </div>

      <!-- Smart Suggestions UI -->
      <div class="max-w-md mx-auto bg-slate-50 p-6 rounded-2xl border border-slate-200/60 text-left space-y-3">
        <h4 class="text-xs font-bold text-slate-800 uppercase tracking-wider">Search Suggestions:</h4>
        <ul class="text-xs text-slate-600 space-y-1.5 list-disc pl-4">
          <li>Check spelling of your keywords.</li>
          <li>Try using more general keywords (e.g. "phone" instead of "iPhone 15 Pro").</li>
          <li>Browse popular categories below.</li>
        </ul>
      </div>

      <!-- Quick Category Links -->
      <div class="flex flex-wrap items-center justify-center gap-2 pt-2">
        <NuxtLink to="/products" class="px-4 py-2 bg-indigo-50 text-indigo-600 font-bold text-xs rounded-xl hover:bg-indigo-100">
          Browse All Products
        </NuxtLink>
        <NuxtLink to="/flash-sale" class="px-4 py-2 bg-amber-50 text-amber-600 font-bold text-xs rounded-xl hover:bg-amber-100">
          Explore Flash Sales ⚡
        </NuxtLink>
      </div>
    </div>

    <!-- Search Results Grid -->
    <div v-else-if="products && products.length > 0" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
      <ProductCard
        v-for="product in products"
        :key="product.id"
        :product="product"
      />
    </div>
  </div>
</template>

<script setup lang="ts">
import { useRoute, useRouter } from 'vue-router';

const route = useRoute();
const router = useRouter();
const { $api } = useNuxtApp();

const searchQuery = ref((route.query.q as string) || '');
let debounceTimer: ReturnType<typeof setTimeout> | null = null;

function handleInput() {
  if (debounceTimer) clearTimeout(debounceTimer);
  debounceTimer = setTimeout(() => {
    router.push({ query: { q: searchQuery.value } });
  }, 300);
}

const { data: responseData, pending } = await useAsyncData('search-products', async () => {
  if (!searchQuery.value) return { data: [], meta: {} };
  const res = await $api.get('/products', { params: { search: searchQuery.value } });
  return res.data;
}, { watch: [() => route.query.q] });

const products = computed(() => responseData.value?.data || []);
const meta = computed(() => responseData.value?.meta || {});
</script>
