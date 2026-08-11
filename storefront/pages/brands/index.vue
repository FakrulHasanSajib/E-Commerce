<template>
  <div class="space-y-6">
    <div class="bg-white p-6 rounded-2xl border border-slate-100 shadow-xs">
      <h1 class="text-2xl font-black text-slate-900">Featured Brands</h1>
      <p class="text-slate-500 text-xs mt-1">Browse authentic products from official brand partners.</p>
    </div>

    <!-- Brands Grid -->
    <div v-if="pending" class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-6 gap-6">
      <div v-for="i in 12" :key="i" class="h-32 bg-white rounded-2xl animate-pulse border border-slate-100"></div>
    </div>

    <div v-else-if="brands && brands.length > 0" class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-6 gap-6">
      <NuxtLink
        v-for="brand in brands"
        :key="brand.id"
        :to="{ path: '/products', query: { brand: brand.slug } }"
        class="bg-white p-6 rounded-2xl border border-slate-100 shadow-xs hover:shadow-md transition-all flex flex-col items-center justify-center text-center group"
      >
        <div class="w-16 h-16 bg-slate-50 rounded-xl mb-3 flex items-center justify-center overflow-hidden">
          <img v-if="brand.logo_url" :src="brand.logo_url" :alt="brand.name" class="w-full h-full object-contain p-2 group-hover:scale-110 transition-transform" />
          <span v-else class="text-xl font-black text-indigo-600 uppercase">{{ brand.name.charAt(0) }}</span>
        </div>
        <span class="font-bold text-slate-900 text-xs group-hover:text-indigo-600 transition-colors">{{ brand.name }}</span>
      </NuxtLink>
    </div>
  </div>
</template>

<script setup lang="ts">
const { $api } = useNuxtApp();

const { data: brands, pending } = await useAsyncData('brands-list', async () => {
  try {
    const res = await $api.get('/brands');
    return res.data?.data || [];
  } catch (e) {
    return [];
  }
});
</script>
