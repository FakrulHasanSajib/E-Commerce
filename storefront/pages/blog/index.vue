<template>
  <div class="space-y-6">
    <div class="bg-white p-6 rounded-2xl border border-slate-100 shadow-xs">
      <h1 class="text-2xl font-black text-slate-900">LaraShop Official Blog</h1>
      <p class="text-slate-500 text-xs mt-1">Buying guides, product reviews, and e-commerce insights.</p>
    </div>

    <!-- Posts Grid -->
    <div v-if="pending" class="grid grid-cols-1 md:grid-cols-3 gap-6">
      <div v-for="i in 3" :key="i" class="h-64 bg-white rounded-2xl animate-pulse border"></div>
    </div>

    <div v-else-if="posts && posts.length > 0" class="grid grid-cols-1 md:grid-cols-3 gap-6">
      <NuxtLink
        v-for="post in posts"
        :key="post.id"
        :to="`/blog/${post.slug}`"
        class="bg-white rounded-2xl border border-slate-100 shadow-xs hover:shadow-md transition-all overflow-hidden flex flex-col group"
      >
        <img :src="post.featured_image_url || '/placeholder-blog.png'" class="w-full h-44 object-cover group-hover:scale-105 transition-transform" />
        <div class="p-6 flex-1 flex flex-col space-y-2">
          <span class="text-[10px] font-bold uppercase text-indigo-600 tracking-wider">Guide</span>
          <h3 class="font-bold text-slate-900 text-sm group-hover:text-indigo-600 transition-colors">{{ post.title }}</h3>
          <p class="text-xs text-slate-500 line-clamp-2 mt-auto">{{ post.excerpt }}</p>
        </div>
      </NuxtLink>
    </div>
  </div>
</template>

<script setup lang="ts">
const { $api } = useNuxtApp();

const { data: posts, pending } = await useAsyncData('blog-posts-list', async () => {
  try {
    const res = await $api.get('/blog/posts');
    return res.data?.data || [];
  } catch (e) {
    return [];
  }
});
</script>
