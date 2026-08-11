<template>
  <nav class="bg-slate-900 text-white text-xs font-semibold border-t border-slate-800">
    <div class="max-w-7xl mx-auto px-4 md:px-8 flex items-center gap-6 overflow-x-auto py-2.5">
      <!-- All Categories Dropdown Trigger -->
      <div class="relative group shrink-0">
        <button class="flex items-center gap-2 px-3 py-1.5 bg-indigo-600 hover:bg-indigo-700 rounded-lg text-white font-bold transition-colors">
          <span>☰</span>
          <span>All Categories</span>
        </button>

        <!-- Mega Menu Dropdown -->
        <div class="absolute left-0 top-full mt-1.5 w-64 bg-white text-slate-900 rounded-xl shadow-2xl border border-slate-100 py-2 hidden group-hover:block z-50">
          <div v-if="pending" class="px-4 py-3 text-slate-400 text-xs flex items-center gap-2">
            <span class="w-3 h-3 border-2 border-indigo-600 border-t-transparent rounded-full animate-spin"></span>
            Loading categories...
          </div>
          <div v-else-if="categories && categories.length > 0">
            <div v-for="cat in categories" :key="cat.id" class="group/item relative">
              <NuxtLink
                :to="{ path: '/products', query: { category: cat.slug } }"
                class="flex items-center justify-between px-4 py-2 hover:bg-slate-50 text-slate-700 font-medium transition-colors"
              >
                <span>{{ cat.name }}</span>
                <span v-if="cat.children && cat.children.length" class="text-slate-400 text-[10px]">▶</span>
              </NuxtLink>

              <!-- Nested Children Submenu -->
              <div
                v-if="cat.children && cat.children.length"
                class="absolute left-full top-0 ml-1 w-56 bg-white rounded-xl shadow-2xl border border-slate-100 py-2 hidden group-hover/item:block"
              >
                <NuxtLink
                  v-for="sub in cat.children"
                  :key="sub.id"
                  :to="{ path: '/products', query: { category: sub.slug } }"
                  class="block px-4 py-1.5 text-xs text-slate-600 hover:bg-slate-50 hover:text-indigo-600"
                >
                  {{ sub.name }}
                </NuxtLink>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Quick Nav Links -->
      <NuxtLink to="/products" class="hover:text-indigo-400 transition-colors shrink-0">
        All Products
      </NuxtLink>
      <NuxtLink to="/flash-sale" class="text-amber-400 hover:text-amber-300 font-bold transition-colors shrink-0">
        ⚡ Flash Sales
      </NuxtLink>
      <NuxtLink to="/brands" class="hover:text-indigo-400 transition-colors shrink-0">
        Brands
      </NuxtLink>
      <NuxtLink to="/compare" class="hover:text-indigo-400 transition-colors shrink-0">
        Compare Products
      </NuxtLink>
    </div>
  </nav>
</template>

<script setup lang="ts">
const { $api } = useNuxtApp();

interface CategoryNode {
  id: number;
  name: string;
  slug: string;
  children?: CategoryNode[];
}

const { data: categories, pending } = await useAsyncData<CategoryNode[]>('categories-mega-menu', async () => {
  try {
    const res = await $api.get('/categories/tree');
    return res.data?.data || [];
  } catch (e) {
    return [];
  }
});
</script>
