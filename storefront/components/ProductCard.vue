<template>
  <div
    :class="[
      'bg-white rounded-3xl border border-slate-100 shadow-xs hover:shadow-xl hover:-translate-y-1 transition-all duration-300 group overflow-hidden flex flex-col',
      viewMode === 'list' ? 'flex-row items-center p-4 gap-6' : 'h-full'
    ]"
  >
    <!-- Image Thumbnail Container -->
    <NuxtLink
      :to="`/products/${product.slug}`"
      :class="[
        viewMode === 'list' ? 'w-44 h-44 shrink-0' : 'w-full aspect-square',
        'relative bg-slate-100 overflow-hidden flex items-center justify-center cursor-pointer block group-hover:bg-slate-50'
      ]"
    >
      <img
        :src="resolvedImageUrl"
        @error="onImageError"
        :alt="product.name"
        width="300"
        height="300"
        loading="lazy"
        class="w-full h-full object-cover group-hover:scale-108 transition-transform duration-500"
      />

      <!-- Sale / Discount Badge -->
      <div v-if="product.sale_price" class="absolute top-3 left-3 px-2.5 py-1 bg-gradient-to-r from-rose-500 to-pink-600 text-white font-black text-[10px] uppercase rounded-xl shadow-md shadow-rose-500/30 z-10 tracking-wider">
        SAVE {{ discountPercentage }}%
      </div>

      <!-- Quick Action Overlay Buttons -->
      <div class="absolute top-3 right-3 flex flex-col gap-2 opacity-0 group-hover:opacity-100 transition-opacity duration-300 z-10">
        <button
          @click.prevent="toggleCompare(product)"
          class="w-8 h-8 rounded-xl bg-white/90 backdrop-blur-md shadow-md flex items-center justify-center text-slate-700 hover:text-indigo-600 hover:scale-110 transition-all text-xs"
          title="Compare Product"
        >
          ⚖️
        </button>
      </div>
    </NuxtLink>

    <!-- Product Details -->
    <div :class="[viewMode === 'list' ? 'flex-1' : 'p-5 flex flex-col flex-1']">
      <div class="flex items-center justify-between gap-2 mb-1.5">
        <span class="text-[10px] font-black text-indigo-600 uppercase tracking-widest bg-indigo-50 px-2 py-0.5 rounded-lg">
          {{ product.brand?.name || 'LaraShop' }}
        </span>
        <span
          :class="['text-[10px] font-bold px-2 py-0.5 rounded-full', product.stock_quantity > 0 ? (product.stock_quantity <= 5 ? 'bg-amber-100 text-amber-800' : 'bg-emerald-100 text-emerald-800') : 'bg-rose-100 text-rose-800']"
        >
          {{ product.stock_quantity > 0 ? (product.stock_quantity <= 5 ? `Low Stock` : 'In Stock') : 'Out of Stock' }}
        </span>
      </div>

      <NuxtLink :to="`/products/${product.slug}`" class="font-bold text-slate-900 text-sm hover:text-indigo-600 transition-colors line-clamp-2 mb-3 leading-snug">
        {{ product.name }}
      </NuxtLink>

      <!-- Pricing -->
      <div class="flex items-baseline gap-2 mb-4">
        <span class="text-lg font-black text-slate-900">
          ৳{{ product.sale_price || product.price }}
        </span>
        <span v-if="product.sale_price" class="text-xs text-slate-400 line-through font-semibold">
          ৳{{ product.price }}
        </span>
      </div>

      <!-- Quick Add to Cart Action -->
      <div class="mt-auto pt-2 flex items-center gap-2">
        <button
          @click.prevent="quickAddToCart"
          :disabled="product.stock_quantity <= 0"
          class="w-full py-2.5 bg-slate-900 hover:bg-indigo-600 text-white font-bold text-xs rounded-2xl shadow-md transition-all flex items-center justify-center gap-1.5 disabled:opacity-40 group-hover:bg-indigo-600"
        >
          <span>🛒</span>
          <span>Add to Cart</span>
        </button>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue';
import { useCartStore } from '~/stores/cart';
import { useToastStore } from '~/stores/toast';

const props = withDefaults(defineProps<{
  product: any;
  viewMode?: 'grid' | 'list';
}>(), {
  viewMode: 'grid',
});

const cartStore = useCartStore();
const toastStore = useToastStore();
const imageFailed = ref(false);

const placeholderImages: Record<string, string> = {
  'iphone': 'https://images.unsplash.com/photo-1511707171634-5f897ff02aa9?auto=format&fit=crop&w=600&q=80',
  'nike': 'https://images.unsplash.com/photo-1542291026-7eec264c27ff?auto=format&fit=crop&w=600&q=80',
  'laravel': 'https://images.unsplash.com/photo-1516321318423-f06f85e504b3?auto=format&fit=crop&w=600&q=80',
  'default': 'https://images.unsplash.com/photo-1523275335684-37898b6baf30?auto=format&fit=crop&w=600&q=80',
};

const discountPercentage = computed(() => {
  if (!props.product?.sale_price || !props.product?.price) return 0;
  const original = parseFloat(props.product.price);
  const sale = parseFloat(props.product.sale_price);
  return Math.round(((original - sale) / original) * 100);
});

const resolvedImageUrl = computed(() => {
  if (imageFailed.value) return getFallback();
  const url = props.product?.primary_image_url || props.product?.image_url;
  if (!url) return getFallback();
  if (url.startsWith('http://') || url.startsWith('https://')) return url;
  return `http://localhost:8000${url.startsWith('/') ? '' : '/'}${url}`;
});

function getFallback() {
  const name = props.product?.name?.toLowerCase() || '';
  if (name.includes('iphone') || name.includes('phone')) return placeholderImages['iphone'];
  if (name.includes('nike') || name.includes('shoe')) return placeholderImages['nike'];
  if (name.includes('laravel') || name.includes('book')) return placeholderImages['laravel'];
  return placeholderImages['default'];
}

function onImageError() {
  imageFailed.value = true;
}

function quickAddToCart() {
  cartStore.addItem({
    id: props.product.id,
    product_id: props.product.id,
    name: props.product.name,
    price: parseFloat(props.product.sale_price || props.product.price),
    quantity: 1,
    image_url: resolvedImageUrl.value,
  });
}

function toggleCompare(product: any) {
  if (!import.meta.client) return;
  const raw = localStorage.getItem('compare_products');
  let list = raw ? JSON.parse(raw) : [];
  if (!list.find((p: any) => p.id === product.id)) {
    list.push(product);
    localStorage.setItem('compare_products', JSON.stringify(list));
    toastStore.show(`${product.name} added to comparison tool!`, 'success', 'Product Comparison');
  } else {
    toastStore.show(`${product.name} is already in comparison!`, 'info', 'Product Comparison');
  }
}
</script>
