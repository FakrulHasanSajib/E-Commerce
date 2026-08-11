<template>
  <div class="space-y-6">
    <div class="bg-white p-6 rounded-2xl border border-slate-100 shadow-xs flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-black text-slate-900">My Wishlist</h1>
        <p class="text-slate-500 text-xs mt-1">{{ wishlistItems.length }} saved items — move to cart when ready.</p>
      </div>
    </div>

    <!-- Loading Skeleton -->
    <div v-if="pending" class="grid grid-cols-1 md:grid-cols-3 gap-6">
      <div v-for="i in 3" :key="i" class="h-56 bg-white rounded-2xl animate-pulse border border-slate-100"></div>
    </div>

    <!-- Wishlist Grid -->
    <div v-else-if="wishlistItems.length > 0" class="grid grid-cols-1 md:grid-cols-3 gap-6">
      <div
        v-for="item in wishlistItems"
        :key="item.product_id"
        class="bg-white rounded-2xl border border-slate-100 shadow-xs hover:shadow-md transition-all overflow-hidden"
      >
        <NuxtLink :to="`/products/${item.product?.slug}`">
          <img
            :src="item.product?.thumbnail_url || '/placeholder.png'"
            class="w-full h-44 object-cover bg-slate-100"
          />
        </NuxtLink>
        <div class="p-4 space-y-3">
          <NuxtLink :to="`/products/${item.product?.slug}`" class="font-bold text-slate-900 text-sm hover:text-indigo-600 transition-colors line-clamp-2">
            {{ item.product?.name }}
          </NuxtLink>

          <div class="flex items-center justify-between">
            <div class="font-black text-slate-900 text-base">
              ৳{{ item.product?.sale_price || item.product?.price }}
              <span v-if="item.product?.sale_price" class="text-xs text-slate-400 line-through ml-1">৳{{ item.product?.price }}</span>
            </div>
            <span :class="['text-[10px] font-bold uppercase px-2 py-0.5 rounded', item.product?.stock_quantity > 0 ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-600']">
              {{ item.product?.stock_quantity > 0 ? 'In Stock' : 'Out of Stock' }}
            </span>
          </div>

          <div class="flex gap-2">
            <button
              @click="moveToCart(item)"
              :disabled="!item.product?.stock_quantity"
              class="flex-1 py-2 bg-indigo-600 hover:bg-indigo-700 disabled:bg-slate-200 disabled:text-slate-400 text-white font-bold text-xs rounded-xl transition-all"
            >
              Add to Cart
            </button>
            <button
              @click="removeFromWishlist(item.product_id)"
              class="p-2 text-rose-400 hover:text-rose-600 hover:bg-rose-50 rounded-xl transition-all"
              title="Remove from wishlist"
            >
              🗑️
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Empty State -->
    <div v-else class="bg-white p-16 rounded-2xl border border-slate-100 text-center space-y-4">
      <div class="text-5xl">💝</div>
      <h3 class="font-bold text-slate-900 text-lg">Your Wishlist is Empty</h3>
      <p class="text-slate-500 text-xs">Browse our catalog and save your favorite items here.</p>
      <NuxtLink to="/products" class="inline-block px-6 py-3 bg-indigo-600 text-white font-bold text-xs rounded-xl shadow-md hover:bg-indigo-700 transition-all">
        Explore Products
      </NuxtLink>
    </div>
  </div>
</template>

<script setup lang="ts">
import { useCartStore } from '~/stores/cart';
definePageMeta({ middleware: ['auth'], layout: 'account' });

const { $api } = useNuxtApp();
const cartStore = useCartStore();

const { data: wishlistItems, pending, refresh } = await useAsyncData('customer-wishlist', async () => {
  try {
    const res = await $api.get('/customer/wishlist');
    return res.data?.data || [];
  } catch {
    return [];
  }
});

async function moveToCart(item: any) {
  try {
    await cartStore.addItem(item.product_id, 1);
    await removeFromWishlist(item.product_id);
    alert(`${item.product?.name} moved to cart!`);
  } catch (e: any) {
    alert(e.response?.data?.message || 'Failed to add to cart.');
  }
}

async function removeFromWishlist(productId: number) {
  try {
    await $api.delete(`/customer/wishlist/${productId}`);
    refresh();
  } catch (e: any) {
    alert(e.response?.data?.message || 'Failed to remove from wishlist.');
  }
}
</script>

