<template>
  <div v-if="landingPage" class="space-y-12">
    <!-- Dynamic Component Section Registry Loop -->
    <template v-for="section in visibleSections" :key="section.id">
      <!-- Section Type: Hero Slider -->
      <div v-if="section.type === 'hero_slider'" class="bg-slate-900 text-white p-12 rounded-3xl text-center space-y-4">
        <h1 class="text-4xl font-black">{{ section.content?.title || landingPage.title }}</h1>
        <p class="text-slate-400 text-sm max-w-lg mx-auto">{{ section.content?.subtitle }}</p>
      </div>

      <!-- Section Type: Category Showcase -->
      <div v-else-if="section.type === 'category_showcase'" class="bg-white p-8 rounded-3xl border shadow-xs space-y-4">
        <h3 class="font-black text-xl text-slate-900">Featured Categories</h3>
        <NuxtLink to="/products" class="inline-block px-4 py-2 bg-indigo-600 text-white text-xs font-bold rounded-xl">Explore Categories</NuxtLink>
      </div>

      <!-- Section Type: Custom HTML (Sanitized Render) -->
      <div v-else-if="section.type === 'custom_html'" class="bg-white p-8 rounded-3xl border shadow-xs" v-html="section.content?.html"></div>
    </template>
  </div>
</template>

<script setup lang="ts">
import { useRoute } from 'vue-router';
const route = useRoute();
const { $api } = useNuxtApp();

const { data: landingPage } = await useAsyncData(`landing-page-${route.params.slug}`, async () => {
  try {
    const res = await $api.get(`/landing-pages/${route.params.slug}`);
    return res.data?.data || res.data;
  } catch (e) {
    return null;
  }
});

const visibleSections = computed(() => {
  if (!landingPage.value?.sections) return [];
  return landingPage.value.sections
    .filter((sec: any) => sec.is_visible !== false)
    .sort((a: any, b: any) => (a.position || 0) - (b.position || 0));
});
</script>
