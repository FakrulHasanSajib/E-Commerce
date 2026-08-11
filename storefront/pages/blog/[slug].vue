<template>
  <div v-if="post" class="max-w-3xl mx-auto space-y-6">
    <div class="bg-white p-8 rounded-3xl border border-slate-100 shadow-xs space-y-4">
      <span class="text-xs font-bold text-indigo-600 uppercase tracking-widest">Published Article</span>
      <h1 class="text-3xl font-black text-slate-900 leading-tight">{{ post.title }}</h1>
      <div class="text-xs text-slate-400 font-mono">By LaraShop Editorial • {{ post.published_at || 'Recent' }}</div>

      <img :src="post.featured_image_url || '/placeholder-blog.png'" class="w-full h-80 object-cover rounded-2xl bg-slate-100" />

      <div class="prose prose-indigo max-w-none text-sm text-slate-700 leading-relaxed pt-4" v-html="post.content"></div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { useRoute } from 'vue-router';
const route = useRoute();
const { $api } = useNuxtApp();

const { data: post } = await useAsyncData(`blog-detail-${route.params.slug}`, async () => {
  const res = await $api.get(`/blog/posts/${route.params.slug}`);
  return res.data?.data || res.data;
});

if (post.value) {
  useHead({
    title: `${post.value.title} - LaraShop Blog`,
    meta: [
      { name: 'description', content: post.value.excerpt || post.value.title },
      { property: 'og:title', content: post.value.title },
      { property: 'og:image', content: post.value.featured_image_url },
    ],
    script: [
      {
        type: 'application/ld+json',
        children: JSON.stringify({
          '@context': 'https://schema.org/',
          '@type': 'Article',
          headline: post.value.title,
          image: post.value.featured_image_url,
          publisher: {
            '@type': 'Organization',
            name: 'LaraShop',
          },
        }),
      },
    ],
  });
}
</script>
