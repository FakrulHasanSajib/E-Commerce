<template>
  <div v-if="product" class="space-y-12">
    <!-- Breadcrumb -->
    <div class="text-xs text-slate-400 font-medium">
      <NuxtLink to="/" class="hover:text-indigo-600">Home</NuxtLink> /
      <NuxtLink to="/products" class="hover:text-indigo-600">Products</NuxtLink> /
      <span class="text-slate-700 font-semibold">{{ product.name }}</span>
    </div>

    <!-- Top Section: Media Gallery + Product Buy Box -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 bg-white p-8 rounded-3xl border border-slate-100 shadow-xs">
      <!-- Media Gallery with Zoom Hover & Thumbnail Strip -->
      <div class="space-y-4">
        <div class="w-full aspect-square bg-slate-100 rounded-2xl overflow-hidden relative group border border-slate-200">
          <img
            :src="resolvedDisplayImage"
            @error="onMainImageError"
            :alt="product.name"
            class="w-full h-full object-cover group-hover:scale-125 transition-transform duration-500 cursor-zoom-in"
          />
        </div>

        <!-- Thumbnail Strip -->
        <div v-if="product.media && product.media.length > 1" class="flex gap-3 overflow-x-auto pb-2">
          <button
            v-for="(img, idx) in product.media"
            :key="idx"
            @click="activeImage = img.original_url"
            :class="['w-16 h-16 rounded-xl overflow-hidden border-2 transition-all shrink-0', activeImage === img.original_url ? 'border-indigo-600' : 'border-slate-200 opacity-70 hover:opacity-100']"
          >
            <img :src="img.original_url" class="w-full h-full object-cover" />
          </button>
        </div>
      </div>

      <!-- Product Information & Variant Selector -->
      <div class="space-y-6 flex flex-col">
        <div>
          <span class="text-xs font-bold text-indigo-600 uppercase tracking-widest">{{ product.brand?.name || 'LaraShop Official' }}</span>
          <h1 class="text-2xl md:text-3xl font-black text-slate-900 mt-1 leading-tight">{{ product.name }}</h1>
        </div>

        <!-- Price Display (Server Resolved Effective Price) -->
        <div class="flex items-baseline gap-3 p-4 bg-slate-50 rounded-2xl border border-slate-100">
          <span class="text-3xl font-black text-slate-900">
            ৳{{ selectedVariant?.sale_price || selectedVariant?.price || product.sale_price || product.price }}
          </span>
          <span v-if="product.sale_price" class="text-sm text-slate-400 line-through font-semibold">
            ৳{{ product.price }}
          </span>
        </div>

        <!-- Stock Availability & Low Stock Warning -->
        <div class="flex items-center gap-3">
          <span
            :class="['px-3 py-1 rounded-full text-xs font-bold', currentStock > 0 ? (currentStock <= 5 ? 'bg-amber-100 text-amber-800' : 'bg-emerald-100 text-emerald-800') : 'bg-rose-100 text-rose-800']"
          >
            {{ currentStock > 0 ? (currentStock <= 5 ? `⚠️ Low Stock (${currentStock} items left)` : '✓ In Stock') : 'Out of Stock' }}
          </span>
        </div>

        <!-- Variant Selector (Swatches) -->
        <div v-if="product.variants && product.variants.length > 0" class="space-y-3">
          <label class="block text-xs font-bold uppercase tracking-wider text-slate-700">Select Variant / Option:</label>
          <div class="flex flex-wrap gap-2">
            <button
              v-for="variant in product.variants"
              :key="variant.id"
              @click="selectedVariant = variant"
              :class="['px-4 py-2 text-xs font-bold rounded-xl border transition-all', selectedVariant?.id === variant.id ? 'border-indigo-600 bg-indigo-50 text-indigo-600 shadow-xs' : 'border-slate-200 text-slate-700 hover:bg-slate-50']"
            >
              {{ variant.name || variant.sku }} - ৳{{ variant.sale_price || variant.price }}
            </button>
          </div>
        </div>

        <!-- Action Buttons -->
        <div class="pt-4 grid grid-cols-2 gap-3 mt-auto">
          <button
            @click="addToCart"
            :disabled="currentStock <= 0"
            class="py-3.5 bg-slate-900 hover:bg-slate-800 text-white font-bold text-sm rounded-2xl shadow-md transition-all disabled:opacity-50"
          >
            🛒 Add to Cart
          </button>
          <button
            @click="buyNow"
            :disabled="currentStock <= 0"
            class="py-3.5 bg-indigo-600 hover:bg-indigo-700 text-white font-black text-sm rounded-2xl shadow-lg shadow-indigo-200 transition-all disabled:opacity-50 flex items-center justify-center gap-1.5"
          >
            <span>⚡</span>
            <span>Buy Now</span>
          </button>
        </div>
      </div>
    </div>

    <!-- Product Reviews & Ratings (Module 14) -->
    <section class="bg-white p-8 rounded-3xl border border-slate-100 shadow-xs space-y-6">
      <div class="flex items-center justify-between border-b border-slate-100 pb-4">
        <h2 class="text-xl font-black text-slate-900">Customer Reviews & Ratings</h2>
        <button
          v-if="authStore.isAuthenticated"
          @click="showReviewModal = true"
          class="px-4 py-2 bg-indigo-50 text-indigo-600 font-bold text-xs rounded-xl hover:bg-indigo-100"
        >
          Write a Review
        </button>
      </div>

      <!-- Rating Breakdown Bar -->
      <div class="flex items-center gap-6 p-6 bg-slate-50 rounded-2xl border border-slate-100 max-w-md">
        <div class="text-center">
          <span class="text-4xl font-black text-slate-900">4.8</span>
          <div class="text-amber-400 text-sm">★★★★★</div>
          <span class="text-slate-400 text-[10px]">Based on reviews</span>
        </div>
        <div class="flex-1 space-y-1 text-xs">
          <div class="flex items-center gap-2">
            <span>5 ★</span>
            <div class="flex-1 h-2 bg-slate-200 rounded-full overflow-hidden">
              <div class="w-4/5 h-full bg-amber-400"></div>
            </div>
          </div>
        </div>
      </div>

      <!-- Review Submission Modal -->
      <div v-if="showReviewModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 p-4">
        <div class="bg-white p-6 rounded-2xl max-w-md w-full space-y-4">
          <h3 class="font-bold text-slate-900 text-base">Write Review for {{ product.name }}</h3>
          <div>
            <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1">Rating</label>
            <select v-model.number="newRating" class="w-full p-2 bg-slate-50 border rounded-xl text-xs">
              <option :value="5">5 Stars - Excellent</option>
              <option :value="4">4 Stars - Very Good</option>
              <option :value="3">3 Stars - Average</option>
            </select>
          </div>
          <div>
            <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1">Comment</label>
            <textarea v-model="newComment" rows="3" class="w-full p-2 bg-slate-50 border rounded-xl text-xs"></textarea>
          </div>
          <div class="flex gap-2 justify-end">
            <button @click="showReviewModal = false" class="px-4 py-2 text-xs font-bold text-slate-500">Cancel</button>
            <button @click="submitReview" class="px-4 py-2 bg-indigo-600 text-white text-xs font-bold rounded-xl">Submit Review</button>
          </div>
        </div>
      </div>
    </section>

    <!-- Product Q&A Section (Module 5 — real API) -->
    <section class="bg-white p-8 rounded-3xl border border-slate-100 shadow-xs space-y-6">
      <div class="flex items-center justify-between border-b border-slate-100 pb-4">
        <h2 class="text-xl font-black text-slate-900">Questions & Answers</h2>
        <button v-if="authStore.isAuthenticated" @click="showAskModal = true"
          class="px-4 py-2 bg-indigo-50 text-indigo-600 font-bold text-xs rounded-xl hover:bg-indigo-100">
          Ask a Question
        </button>
      </div>

      <!-- Questions List -->
      <div v-if="questions.length > 0" class="space-y-4">
        <div v-for="q in questions" :key="q.id" class="p-4 bg-slate-50 rounded-2xl border border-slate-100 text-xs space-y-2">
          <div class="font-bold text-slate-900">Q: {{ q.question }}</div>
          <div v-if="q.answer" class="text-slate-600 pl-3 border-l-2 border-indigo-200">A: {{ q.answer }}</div>
          <div v-else class="text-slate-400 italic pl-3 border-l-2 border-slate-200">Awaiting answer from seller...</div>
          <div class="text-slate-400 text-[10px]">Asked by {{ q.user?.name || 'Customer' }}</div>
        </div>
      </div>
      <div v-else class="text-center py-8 text-slate-400 text-xs">
        No questions yet. Be the first to ask!
      </div>

      <!-- Ask Question Modal -->
      <div v-if="showAskModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 p-4">
        <div class="bg-white p-6 rounded-2xl max-w-md w-full space-y-4 shadow-2xl">
          <h3 class="font-bold text-slate-900">Ask a Question</h3>
          <textarea v-model="newQuestion" rows="3" placeholder="Type your question here..."
            class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs resize-none focus:outline-none focus:border-indigo-400"></textarea>
          <div class="flex gap-2 justify-end">
            <button @click="showAskModal = false" class="px-4 py-2 text-xs font-bold text-slate-500">Cancel</button>
            <button @click="submitQuestion" class="px-4 py-2 bg-indigo-600 text-white text-xs font-bold rounded-xl hover:bg-indigo-700">Submit Question</button>
          </div>
        </div>
      </div>
    </section>

    <!-- Recently Viewed Products (localStorage tracked) -->
    <section v-if="recentlyViewed && recentlyViewed.length > 0" class="space-y-4">
      <h2 class="text-xl font-black text-slate-900">Recently Viewed</h2>
      <div class="grid grid-cols-2 md:grid-cols-4 gap-6">
        <ProductCard v-for="item in recentlyViewed" :key="item.id" :product="item" />
      </div>
    </section>
  </div>
</template>

<script setup lang="ts">
import { useRoute } from 'vue-router';
import { useAuthStore } from '~/stores/auth';
import { useCartStore } from '~/stores/cart';

const route = useRoute();
const { $api } = useNuxtApp();
const authStore = useAuthStore();

const activeImage = ref('');
const mainImageFailed = ref(false);
const selectedVariant = ref<any>(null);

const placeholderImages: Record<string, string> = {
  'iphone': 'https://images.unsplash.com/photo-1511707171634-5f897ff02aa9?auto=format&fit=crop&w=800&q=80',
  'nike': 'https://images.unsplash.com/photo-1542291026-7eec264c27ff?auto=format&fit=crop&w=800&q=80',
  'laravel': 'https://images.unsplash.com/photo-1516321318423-f06f85e504b3?auto=format&fit=crop&w=800&q=80',
  'default': 'https://images.unsplash.com/photo-1523275335684-37898b6baf30?auto=format&fit=crop&w=800&q=80',
};

const resolvedDisplayImage = computed(() => {
  if (mainImageFailed.value) return getFallback();
  const url = activeImage.value || product.value?.primary_image_url || product.value?.image_url;
  if (!url) return getFallback();
  if (url.startsWith('http://') || url.startsWith('https://')) return url;
  return `http://localhost:8000${url.startsWith('/') ? '' : '/'}${url}`;
});

function getFallback() {
  const name = product.value?.name?.toLowerCase() || '';
  if (name.includes('iphone') || name.includes('phone')) return placeholderImages['iphone'];
  if (name.includes('nike') || name.includes('shoe')) return placeholderImages['nike'];
  if (name.includes('laravel') || name.includes('book')) return placeholderImages['laravel'];
  return placeholderImages['default'];
}

function onMainImageError() {
  mainImageFailed.value = true;
}
const showReviewModal = ref(false);
const newRating = ref(5);
const newComment = ref('');
const recentlyViewed = ref<any[]>([]);
const questions = ref<any[]>([]);
const showAskModal = ref(false);
const newQuestion = ref('');

const { data: product } = await useAsyncData(`product-detail-${route.params.slug}`, async () => {
  const res = await $api.get(`/products/${route.params.slug}`);
  return res.data?.data;
});

const currentStock = computed(() => {
  if (selectedVariant.value) return selectedVariant.value.stock_quantity;
  return product.value?.stock_quantity || 0;
});

// Module 16 SEO Meta & JSON-LD Injection
if (product.value) {
  useHead({
    title: `${product.value.name} - LaraShop`,
    meta: [
      { name: 'description', content: product.value.description || product.value.name },
      { property: 'og:title', content: product.value.name },
      { property: 'og:image', content: product.value.primary_image_url },
    ],
    script: [
      {
        type: 'application/ld+json',
        children: JSON.stringify({
          '@context': 'https://schema.org/',
          '@type': 'Product',
          name: product.value.name,
          image: product.value.primary_image_url,
          offers: {
            '@type': 'Offer',
            priceCurrency: 'BDT',
            price: product.value.sale_price || product.value.price,
            availability: currentStock.value > 0 ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
          },
        }),
      },
    ],
  });
}

// Client-side Recently Viewed Storage Sync
onMounted(async () => {
  if (import.meta.client && product.value) {
    const raw = localStorage.getItem('recently_viewed');
    let list = raw ? JSON.parse(raw) : [];
    list = list.filter((p: any) => p.id !== product.value.id);
    list.unshift({
      id: product.value.id,
      name: product.value.name,
      slug: product.value.slug,
      price: product.value.price,
      sale_price: product.value.sale_price,
      primary_image_url: product.value.primary_image_url,
      stock_quantity: product.value.stock_quantity,
    });
    list = list.slice(0, 4);
    localStorage.setItem('recently_viewed', JSON.stringify(list));
    recentlyViewed.value = list;

    // Fetch Q&A
    try {
      const res = await $api.get(`/products/${product.value.id}/questions`);
      questions.value = res.data?.data || [];
    } catch { questions.value = []; }
  }
});

const cartStore = useCartStore();

function addToCart() {
  const itemPrice = selectedVariant.value?.sale_price || selectedVariant.value?.price || product.value?.sale_price || product.value?.price;
  cartStore.addItem({
    id: selectedVariant.value?.id || product.value.id,
    product_id: product.value.id,
    variant_id: selectedVariant.value?.id || null,
    name: product.value.name + (selectedVariant.value ? ` (${selectedVariant.value.name || selectedVariant.value.sku})` : ''),
    price: parseFloat(itemPrice),
    quantity: 1,
    image_url: resolvedDisplayImage.value,
  });
}

function buyNow() {
  addToCart();
  navigateTo('/checkout');
}

async function submitReview() {
  try {
    await $api.post(`/products/${product.value.id}/reviews`, {
      rating: newRating.value,
      comment: newComment.value,
    });
    alert('Review submitted successfully!');
    showReviewModal.value = false;
  } catch (e: any) {
    alert(e.response?.data?.message || 'Failed to submit review.');
  }
}

async function submitQuestion() {
  if (!newQuestion.value.trim()) return;
  try {
    await $api.post(`/products/${product.value.id}/questions`, {
      question: newQuestion.value,
    });
    const res = await $api.get(`/products/${product.value.id}/questions`);
    questions.value = res.data?.data || [];
    newQuestion.value = '';
    showAskModal.value = false;
  } catch (e: any) {
    alert(e.response?.data?.message || 'Failed to submit question.');
  }
}
</script>
