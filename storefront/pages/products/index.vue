<template>
  <div class="space-y-6">
    <!-- Breadcrumb & Header -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-white p-6 rounded-2xl border border-slate-100 shadow-xs">
      <div>
        <div class="text-xs text-slate-400 font-medium mb-1">
          <NuxtLink to="/" class="hover:text-indigo-600">Home</NuxtLink> / <span class="text-slate-700">Catalog</span>
        </div>
        <h1 class="text-2xl font-black text-slate-900">All Products</h1>
      </div>

      <!-- Controls: View Mode Toggle & Sort Dropdown -->
      <div class="flex items-center gap-4">
        <!-- Sort Dropdown -->
        <div class="flex items-center gap-2">
          <label class="text-xs font-bold text-slate-500 uppercase tracking-wider">Sort:</label>
          <select
            v-model="sort"
            @change="updateFilters"
            class="px-3 py-1.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-semibold text-slate-800 focus:outline-hidden"
          >
            <option value="newest">Newest Arrivals</option>
            <option value="price_asc">Price: Low to High</option>
            <option value="price_desc">Price: High to Low</option>
          </select>
        </div>

        <!-- View Mode (Grid vs List) -->
        <div class="flex items-center bg-slate-100 p-1 rounded-xl border border-slate-200">
          <button
            @click="viewMode = 'grid'"
            :class="['px-3 py-1 text-xs font-bold rounded-lg transition-all', viewMode === 'grid' ? 'bg-white text-indigo-600 shadow-xs' : 'text-slate-500']"
          >
            Grid ▦
          </button>
          <button
            @click="viewMode = 'list'"
            :class="['px-3 py-1 text-xs font-bold rounded-lg transition-all', viewMode === 'list' ? 'bg-white text-indigo-600 shadow-xs' : 'text-slate-500']"
          >
            List ☰
          </button>
        </div>
      </div>
    </div>

    <!-- Main Content Area: Sidebar Filters + Products Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-4 gap-8">
      <!-- Faceted Filter Sidebar -->
      <aside class="bg-white p-6 rounded-2xl border border-slate-100 shadow-xs space-y-6 h-fit">
        <div class="flex items-center justify-between border-b border-slate-100 pb-4">
          <h3 class="font-bold text-slate-900 text-sm uppercase tracking-wider">Filters</h3>
          <button @click="resetFilters" class="text-xs text-rose-600 hover:underline font-semibold">Reset All</button>
        </div>

        <!-- Price Range Filter (price_min & price_max exact backend params) -->
        <div>
          <h4 class="text-xs font-bold text-slate-800 uppercase tracking-wider mb-3">Price Range (৳)</h4>
          <div class="flex items-center gap-2">
            <input
              v-model.number="priceMin"
              type="number"
              placeholder="Min"
              class="w-full px-3 py-1.5 bg-slate-50 border border-slate-200 rounded-lg text-xs"
            />
            <span class="text-slate-400 text-xs">-</span>
            <input
              v-model.number="priceMax"
              type="number"
              placeholder="Max"
              class="w-full px-3 py-1.5 bg-slate-50 border border-slate-200 rounded-lg text-xs"
            />
          </div>
          <button @click="updateFilters" class="w-full mt-2 py-1.5 bg-indigo-600 text-white text-xs font-bold rounded-lg hover:bg-indigo-700">
            Apply Price
          </button>
        </div>

        <!-- Faceted Categories Filter (showing meta.facets counts) -->
        <div v-if="facets.categories && Object.keys(facets.categories).length">
          <h4 class="text-xs font-bold text-slate-800 uppercase tracking-wider mb-3">Categories</h4>
          <div class="space-y-1.5 max-h-48 overflow-y-auto text-xs">
            <div
              v-for="(count, name) in facets.categories"
              :key="name"
              class="flex items-center justify-between text-slate-600 hover:text-indigo-600 cursor-pointer"
            >
              <span>{{ name }}</span>
              <span class="px-2 py-0.5 bg-slate-100 text-slate-500 rounded-full font-mono text-[10px]">{{ count }}</span>
            </div>
          </div>
        </div>

        <!-- Faceted Brands Filter (showing meta.facets counts) -->
        <div v-if="facets.brands && Object.keys(facets.brands).length">
          <h4 class="text-xs font-bold text-slate-800 uppercase tracking-wider mb-3">Brands</h4>
          <div class="space-y-1.5 max-h-48 overflow-y-auto text-xs">
            <div
              v-for="(count, name) in facets.brands"
              :key="name"
              class="flex items-center justify-between text-slate-600 hover:text-indigo-600 cursor-pointer"
            >
              <span>{{ name }}</span>
              <span class="px-2 py-0.5 bg-slate-100 text-slate-500 rounded-full font-mono text-[10px]">{{ count }}</span>
            </div>
          </div>
        </div>
      </aside>

      <!-- Products Grid / List -->
      <main class="lg:col-span-3 space-y-6">
        <!-- Skeleton Loader -->
        <div v-if="pending" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
          <div v-for="i in 6" :key="i" class="bg-white h-72 rounded-2xl animate-pulse border border-slate-100"></div>
        </div>

        <!-- Product Items -->
        <div v-else-if="products && products.length > 0" :class="viewMode === 'grid' ? 'grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6' : 'space-y-4'">
          <ProductCard
            v-for="product in products"
            :key="product.id"
            :product="product"
            :view-mode="viewMode"
          />
        </div>

        <!-- Empty State -->
        <div v-else class="bg-white p-12 rounded-2xl border border-slate-100 text-center space-y-4">
          <span class="text-4xl block">🔍</span>
          <h3 class="font-bold text-slate-900 text-lg">No Products Found</h3>
          <p class="text-slate-500 text-xs max-w-sm mx-auto">Try clearing price or category filters to discover products.</p>
          <button @click="resetFilters" class="px-4 py-2 bg-indigo-600 text-white text-xs font-bold rounded-xl">Clear All Filters</button>
        </div>

        <!-- Pagination Bar -->
        <div v-if="meta && meta.last_page > 1" class="flex items-center justify-center gap-2 pt-6">
          <button
            v-for="p in meta.last_page"
            :key="p"
            @click="changePage(p)"
            :class="['w-9 h-9 rounded-xl font-bold text-xs transition-all', currentPage === p ? 'bg-indigo-600 text-white shadow-md' : 'bg-white text-slate-700 border border-slate-200 hover:bg-slate-50']"
          >
            {{ p }}
          </button>
        </div>
      </main>
    </div>
  </div>
</template>

<script setup lang="ts">
import { useRoute, useRouter } from 'vue-router';

const route = useRoute();
const router = useRouter();
const { $api } = useNuxtApp();

const viewMode = ref<'grid' | 'list'>('grid');
const sort = ref((route.query.sort as string) || 'newest');
const priceMin = ref(route.query.price_min ? Number(route.query.price_min) : null);
const priceMax = ref(route.query.price_max ? Number(route.query.price_max) : null);
const currentPage = ref(Number(route.query.page) || 1);

const { data: responseData, pending, refresh } = await useAsyncData('products-listing', async () => {
  const params: any = {
    sort: sort.value,
    page: currentPage.value,
  };

  if (priceMin.value !== null) params.price_min = priceMin.value;
  if (priceMax.value !== null) params.price_max = priceMax.value;
  if (route.query.category) params.category = route.query.category;
  if (route.query.brand) params.brand = route.query.brand;

  const res = await $api.get('/products', { params });
  return res.data;
}, { watch: [() => route.query] });

const products = computed(() => responseData.value?.data || []);
const meta = computed(() => responseData.value?.meta || {});
const facets = computed(() => responseData.value?.meta?.facets || {});

function updateFilters() {
  const query: any = { ...route.query, sort: sort.value, page: 1 };
  if (priceMin.value !== null) query.price_min = priceMin.value;
  if (priceMax.value !== null) query.price_max = priceMax.value;
  router.push({ query });
}

function resetFilters() {
  priceMin.value = null;
  priceMax.value = null;
  sort.value = 'newest';
  router.push({ query: {} });
}

function changePage(p: number) {
  currentPage.value = p;
  router.push({ query: { ...route.query, page: p } });
}
</script>
