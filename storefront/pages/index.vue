<template>
  <div class="space-y-12">
    <!-- Dynamic Hero Banner Carousel (Driven by API / Admin Landing Page Builder if available, with fallbacks) -->
    <div class="relative bg-gradient-to-r from-indigo-950 via-indigo-900 to-slate-950 text-white rounded-3xl overflow-hidden shadow-2xl border border-indigo-500/20">
      <div class="grid grid-cols-1 lg:grid-cols-12 items-center min-h-[380px] p-8 md:p-12 gap-8">
        <!-- Left Content -->
        <div class="lg:col-span-7 space-y-5 z-10">
          <div class="flex items-center gap-2">
            <span class="px-3.5 py-1 bg-amber-500/20 text-amber-300 rounded-full text-xs font-black uppercase tracking-widest border border-amber-500/30 flex items-center gap-1.5">
              <span>⚡</span>
              <span>{{ currentSlide.badge || 'PROMOTIONAL DEAL' }}</span>
            </span>
          </div>

          <h1 class="text-3xl md:text-5xl font-black tracking-tight leading-tight text-white">
            {{ currentSlide.title }}
          </h1>

          <p class="text-indigo-200 text-sm md:text-base leading-relaxed max-w-xl">
            {{ currentSlide.subtitle }}
          </p>

          <div class="pt-2 flex flex-wrap gap-4 items-center">
            <NuxtLink :to="currentSlide.link || '/products'" class="px-7 py-3.5 bg-indigo-600 hover:bg-indigo-500 text-white font-black text-sm rounded-2xl shadow-xl shadow-indigo-600/30 transition-all flex items-center gap-2">
              <span>{{ currentSlide.cta || 'Explore Now' }}</span>
              <span>→</span>
            </NuxtLink>
            <NuxtLink to="/flash-sale" class="px-6 py-3.5 bg-slate-900/80 hover:bg-slate-900 text-amber-400 font-bold text-sm rounded-2xl border border-slate-700 transition-all flex items-center gap-2">
              <span>⚡</span>
              <span>Flash Deals</span>
            </NuxtLink>
          </div>
        </div>

        <!-- Right Banner Image / Graphic Presentation -->
        <div class="lg:col-span-5 relative flex items-center justify-center">
          <div class="w-full h-72 md:h-80 rounded-2xl overflow-hidden border border-white/10 shadow-2xl relative group">
            <img
              :src="currentSlide.image"
              :alt="currentSlide.title"
              class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-700"
            />
            <div class="absolute inset-0 bg-gradient-to-t from-slate-950/80 via-transparent to-transparent"></div>
            <div class="absolute bottom-4 left-4 right-4 p-3 bg-slate-950/80 backdrop-blur-md rounded-xl border border-white/10 flex items-center justify-between text-xs">
              <span class="font-bold text-white">{{ currentSlide.tagline || 'Special Offer' }}</span>
              <span class="font-black text-emerald-400">Limited Time</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Slide Indicator Controls -->
      <div v-if="activeSlides.length > 1" class="absolute bottom-4 left-8 md:left-12 flex gap-2 z-20">
        <button
          v-for="(slide, idx) in activeSlides"
          :key="idx"
          @click="activeSlideIndex = idx"
          :class="['h-2.5 rounded-full transition-all duration-300', activeSlideIndex === idx ? 'w-8 bg-amber-400' : 'w-2.5 bg-white/30 hover:bg-white/60']"
          :title="`Slide ${idx + 1}`"
        ></button>
      </div>
    </div>

    <!-- Active Flash Sale Section -->
    <section v-if="activeFlashSale" class="bg-gradient-to-r from-amber-500/10 via-amber-500/5 to-transparent p-8 rounded-3xl border border-amber-500/20 space-y-6">
      <div class="flex items-center justify-between flex-wrap gap-4">
        <div>
          <div class="flex items-center gap-2 text-amber-600 font-black text-xs uppercase tracking-widest">
            <span>⚡</span>
            <span>Limited Time Deal</span>
          </div>
          <h2 class="text-2xl font-black text-slate-900 mt-1">{{ activeFlashSale.name || 'Flash Sale Deals' }}</h2>
        </div>

        <FlashSaleTimer v-if="activeFlashSale.ends_at" :endsAt="activeFlashSale.ends_at" />
      </div>

      <!-- Flash Sale Deal Products Grid -->
      <div v-if="flashProducts.length" class="grid grid-cols-2 md:grid-cols-4 gap-6">
        <ProductCard v-for="prod in flashProducts" :key="prod.id" :product="prod" />
      </div>
    </section>

    <!-- Category Showcase Tiles -->
    <section v-if="categories.length" class="space-y-6">
      <div class="flex items-center justify-between">
        <h2 class="text-2xl font-black text-slate-900">Explore Top Categories</h2>
        <NuxtLink to="/products" class="text-xs font-bold text-indigo-600 hover:underline">View All Categories →</NuxtLink>
      </div>

      <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-6 gap-4">
        <NuxtLink
          v-for="cat in categories.slice(0, 6)"
          :key="cat.id"
          :to="`/products?category=${cat.slug}`"
          class="bg-white p-5 rounded-2xl border border-slate-100 shadow-xs hover:shadow-md hover:border-indigo-200 transition-all text-center space-y-2 group"
        >
          <div class="w-12 h-12 bg-indigo-50 group-hover:bg-indigo-600 group-hover:text-white text-indigo-600 rounded-2xl flex items-center justify-center mx-auto text-xl transition-all">
            📁
          </div>
          <div class="font-bold text-slate-900 text-xs truncate group-hover:text-indigo-600 transition-colors">{{ cat.name }}</div>
        </NuxtLink>
      </div>
    </section>

    <!-- Trending / Featured Products Grid -->
    <section class="space-y-6">
      <div class="flex items-center justify-between">
        <div>
          <h2 class="text-2xl font-black text-slate-900">Trending Products</h2>
          <p class="text-xs text-slate-500 mt-1">Handpicked quality products with fast nationwide shipping.</p>
        </div>
        <NuxtLink to="/products" class="text-xs font-bold text-indigo-600 hover:underline">Browse Catalog →</NuxtLink>
      </div>

      <div v-if="pending" class="grid grid-cols-2 md:grid-cols-4 gap-6">
        <div v-for="i in 8" :key="i" class="h-64 bg-white rounded-2xl animate-pulse border border-slate-100"></div>
      </div>

      <div v-else-if="products.length" class="grid grid-cols-2 md:grid-cols-4 gap-6">
        <ProductCard v-for="prod in products" :key="prod.id" :product="prod" />
      </div>

      <div v-else class="bg-white p-12 rounded-2xl border border-slate-100 text-center text-slate-500 text-xs">
        No products available yet. Check back soon!
      </div>
    </section>

    <!-- Brands Logo Bar -->
    <section v-if="brands.length" class="bg-white p-8 rounded-3xl border border-slate-100 shadow-xs space-y-4">
      <h3 class="text-xs font-mono uppercase text-slate-400 text-center tracking-widest">Official Brand Partners</h3>
      <div class="flex items-center justify-center flex-wrap gap-8">
        <NuxtLink v-for="b in brands" :key="b.id" :to="`/products?brand=${b.slug}`"
          class="font-black text-slate-400 hover:text-indigo-600 text-lg transition-colors tracking-wide">
          {{ b.name }}
        </NuxtLink>
      </div>
    </section>

    <!-- Value Proposition Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
      <div class="bg-white p-6 rounded-2xl shadow-xs border border-slate-100 flex items-center gap-4">
        <div class="w-12 h-12 rounded-xl bg-indigo-100 text-indigo-600 text-2xl flex items-center justify-center font-bold">
          ⚡
        </div>
        <div>
          <h3 class="font-bold text-slate-900 text-sm">Lightning Fast SSR</h3>
          <p class="text-xs text-slate-500">Instant page rendering & SEO metadata</p>
        </div>
      </div>

      <div class="bg-white p-6 rounded-2xl shadow-xs border border-slate-100 flex items-center gap-4">
        <div class="w-12 h-12 rounded-xl bg-emerald-100 text-emerald-600 text-2xl flex items-center justify-center font-bold">
          🛡️
        </div>
        <div>
          <h3 class="font-bold text-slate-900 text-sm">PCI-DSS & 2FA Safe</h3>
          <p class="text-xs text-slate-500">RFC 6238 TOTP & Encrypted storage</p>
        </div>
      </div>

      <div class="bg-white p-6 rounded-2xl shadow-xs border border-slate-100 flex items-center gap-4">
        <div class="w-12 h-12 rounded-xl bg-amber-100 text-amber-600 text-2xl flex items-center justify-center font-bold">
          🚚
        </div>
        <div>
          <h3 class="font-bold text-slate-900 text-sm">Nationwide Delivery</h3>
          <p class="text-xs text-slate-500">Live shipping zone calculation</p>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';

const { $api } = useNuxtApp();

const activeSlideIndex = ref(0);

const defaultSlides = [
  {
    badge: 'EID MEGA SALE • UP TO 50% OFF',
    title: 'Next-Gen Shopping Experience',
    subtitle: 'Shop authentic electronics, fashion, and lifestyle items with instant wallet refunds, 2FA security, and fast nationwide delivery.',
    cta: 'Explore Catalog',
    link: '/products',
    tagline: 'Premium Smartphones & Tech',
    image: 'https://images.unsplash.com/photo-1511707171634-5f897ff02aa9?auto=format&fit=crop&w=800&q=80',
  },
  {
    badge: 'NEW ARRIVALS 2026',
    title: 'Upgrade Your Lifestyle Today',
    subtitle: 'Discover genuine branded sneakers, watches, and smart wearables with guaranteed authenticity and cashback points.',
    cta: 'Shop New Arrivals',
    link: '/products?sort=newest',
    tagline: 'Authentic Footwear & Apparel',
    image: 'https://images.unsplash.com/photo-1542291026-7eec264c27ff?auto=format&fit=crop&w=800&q=80',
  },
  {
    badge: 'FLASH DEAL SPECIAL',
    title: 'Unbeatable Tech Discounts',
    subtitle: 'Get top gadgets with zero-interest installments, 1-year brand warranty, and same-day delivery in Dhaka city.',
    cta: 'Grab Flash Deals',
    link: '/flash-sale',
    tagline: 'Smart Watches & Gadgets',
    image: 'https://images.unsplash.com/photo-1523275335684-37898b6baf30?auto=format&fit=crop&w=800&q=80',
  },
];

// Parallel SSR Data Fetching (Including API Landing Page / Banners)
const [
  { data: productsData, pending },
  { data: categoriesData },
  { data: brandsData },
  { data: flashData },
  { data: landingData }
] = await Promise.all([
  useAsyncData('home-products', async () => {
    try {
      const res = await $api.get('/products', { params: { limit: 8 } });
      return res.data?.data || [];
    } catch { return []; }
  }),
  useAsyncData('home-categories', async () => {
    try {
      const res = await $api.get('/categories');
      return res.data?.data || [];
    } catch { return []; }
  }),
  useAsyncData('home-brands', async () => {
    try {
      const res = await $api.get('/brands');
      return res.data?.data || [];
    } catch { return []; }
  }),
  useAsyncData('home-flash-sale', async () => {
    try {
      const res = await $api.get('/flash-sales/active');
      return res.data;
    } catch { return null; }
  }),
  useAsyncData('home-landing-page', async () => {
    try {
      const res = await $api.get('/landing-pages/home');
      return res.data?.data;
    } catch { return null; }
  }),
]);

const products = computed(() => {
  const val = productsData.value;
  if (!val) return [];
  if (Array.isArray(val)) return val;
  if (Array.isArray(val.data)) return val.data;
  return [];
});

const categories = computed(() => {
  const val = categoriesData.value;
  if (!val) return [];
  if (Array.isArray(val)) return val;
  if (Array.isArray(val.data)) return val.data;
  return [];
});

const brands = computed(() => {
  const val = brandsData.value;
  if (!val) return [];
  if (Array.isArray(val)) return val;
  if (Array.isArray(val.data)) return val.data;
  return [];
});

const activeFlashSale = computed(() => flashData.value?.data || flashData.value || null);
const flashProducts = computed(() => activeFlashSale.value?.products || products.value.slice(0, 4));

// Dynamic Banners from Admin Landing Page Builder if available
const activeSlides = computed(() => {
  const heroSection = landingData.value?.sections?.find((s: any) => s.type === 'hero_slider');
  if (heroSection?.settings?.slides?.length) {
    return heroSection.settings.slides;
  }
  return defaultSlides;
});

const currentSlide = computed(() => activeSlides.value[activeSlideIndex.value] || defaultSlides[0]);

// Auto slide every 5 seconds & client fallback fetch if needed
onMounted(async () => {
  if (import.meta.client) {
    if (activeSlides.value.length > 1) {
      setInterval(() => {
        activeSlideIndex.value = (activeSlideIndex.value + 1) % activeSlides.value.length;
      }, 5000);
    }

    // Client fallback fetch if SSR returned empty
    if (products.value.length === 0) {
      try {
        const res = await $api.get('/products', { params: { limit: 8 } });
        productsData.value = res.data?.data || res.data || [];
      } catch {}
    }
  }
});
</script>
