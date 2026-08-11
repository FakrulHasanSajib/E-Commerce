<template>
  <div class="space-y-8 max-w-5xl mx-auto">
    <!-- Cart Header -->
    <div class="bg-gradient-to-r from-slate-900 via-indigo-950 to-slate-900 text-white p-8 rounded-3xl shadow-xl flex items-center justify-between flex-wrap gap-4 border border-indigo-500/20">
      <div>
        <span class="px-3 py-1 bg-indigo-500/20 text-indigo-300 rounded-full text-xs font-bold uppercase tracking-widest border border-indigo-400/20">
          Shopping Cart
        </span>
        <h1 class="text-2xl md:text-3xl font-black tracking-tight mt-1">Review Your Items</h1>
        <p class="text-indigo-200 text-xs mt-1">Verify quantities and apply coupons before proceeding to checkout.</p>
      </div>

      <div class="text-right font-mono">
        <div class="text-xs text-indigo-300 uppercase font-bold">Total Payable</div>
        <div class="text-3xl font-black text-amber-400">৳{{ cartStore.grandTotal }}</div>
      </div>
    </div>

    <!-- Free Shipping Goal Progress Bar -->
    <div class="bg-white p-5 rounded-2xl border border-slate-100 shadow-xs space-y-2">
      <div class="flex justify-between items-center text-xs font-bold">
        <span class="flex items-center gap-2 text-slate-800">
          <span>🚚</span>
          <span v-if="freeShippingRemaining <= 0" class="text-emerald-600">Congratulations! You've unlocked FREE Nationwide Shipping!</span>
          <span v-else class="text-slate-700">Add <strong>৳{{ freeShippingRemaining }}</strong> more for FREE Shipping!</span>
        </span>
        <span class="text-indigo-600 font-mono">{{ freeShippingPercentage }}%</span>
      </div>
      <div class="w-full h-2.5 bg-slate-100 rounded-full overflow-hidden">
        <div class="h-full bg-gradient-to-r from-indigo-500 to-emerald-500 rounded-full transition-all duration-500" :style="{ width: freeShippingPercentage + '%' }"></div>
      </div>
    </div>

    <!-- Empty Cart State -->
    <div v-if="cartStore.items.length === 0" class="bg-white p-16 rounded-3xl border border-slate-100 text-center space-y-4 shadow-xs">
      <div class="w-20 h-20 bg-indigo-50 text-indigo-600 rounded-3xl flex items-center justify-center text-4xl mx-auto">
        🛒
      </div>
      <h3 class="font-black text-slate-900 text-xl">Your Cart is Currently Empty</h3>
      <p class="text-slate-500 text-xs max-w-sm mx-auto">Discover thousands of authentic products with fast nationwide shipping across Bangladesh.</p>
      <div class="pt-2">
        <NuxtLink to="/products" class="px-8 py-3.5 bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs rounded-2xl shadow-lg shadow-indigo-500/25 transition-all inline-block">
          Explore All Products →
        </NuxtLink>
      </div>
    </div>

    <!-- Cart Items Grid + Order Summary -->
    <div v-else class="grid grid-cols-1 lg:grid-cols-3 gap-8">
      <!-- Line Items List -->
      <div class="lg:col-span-2 space-y-4">
        <div
          v-for="item in cartStore.items"
          :key="item.id"
          class="bg-white p-5 rounded-3xl border border-slate-100 shadow-xs hover:shadow-md transition-all flex items-center justify-between gap-5 group"
        >
          <img :src="resolveCartImage(item)" :alt="item.name" class="w-20 h-20 object-cover rounded-2xl bg-slate-100 shrink-0 border border-slate-100" />
          
          <div class="flex-1 min-w-0">
            <h4 class="font-bold text-slate-900 text-sm truncate group-hover:text-indigo-600 transition-colors">{{ item.name }}</h4>
            <div class="text-xs font-black text-indigo-600 mt-1">৳{{ item.price }}</div>
          </div>

          <!-- Quantity Controls -->
          <div class="flex items-center bg-slate-50 rounded-2xl border border-slate-200/80 p-1">
            <button @click="cartStore.updateQuantity(item.id, Math.max(1, item.quantity - 1))" class="w-7 h-7 flex items-center justify-center text-slate-600 font-bold hover:bg-white rounded-xl transition-all text-xs">-</button>
            <span class="px-3 text-xs font-black font-mono text-slate-900">{{ item.quantity }}</span>
            <button @click="cartStore.updateQuantity(item.id, item.quantity + 1)" class="w-7 h-7 flex items-center justify-center text-slate-600 font-bold hover:bg-white rounded-xl transition-all text-xs">+</button>
          </div>

          <!-- Item Subtotal & Delete -->
          <div class="text-right shrink-0">
            <div class="font-black text-slate-900 text-sm">৳{{ item.price * item.quantity }}</div>
            <button @click="cartStore.removeItem(item.id)" class="text-[11px] text-rose-500 hover:text-rose-600 font-bold hover:underline mt-1">Remove</button>
          </div>
        </div>

        <div class="flex items-center justify-between pt-2">
          <NuxtLink to="/products" class="text-xs font-bold text-indigo-600 hover:underline flex items-center gap-1">
            <span>←</span>
            <span>Continue Shopping</span>
          </NuxtLink>
        </div>
      </div>

      <!-- Order Summary & Coupons Box -->
      <div class="space-y-6">
        <!-- Coupon & Gift Card Box -->
        <div class="bg-white p-6 rounded-3xl border border-slate-100 shadow-xs space-y-5">
          <h3 class="font-bold text-slate-900 text-xs uppercase tracking-wider flex items-center gap-2 border-b border-slate-100 pb-3">
            <span>🏷️</span>
            <span>Promotions & Discounts</span>
          </h3>

          <!-- Coupon Input -->
          <div class="space-y-2">
            <label class="block text-[11px] text-slate-500 font-bold">Have a Coupon Code?</label>
            <div class="flex gap-2">
              <input v-model="couponCode" type="text" placeholder="FREESHIP" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs uppercase font-mono font-bold focus:outline-none focus:border-indigo-500" />
              <button @click="handleApplyCoupon" class="px-4 py-2.5 bg-indigo-600 text-white text-xs font-bold rounded-xl hover:bg-indigo-700 shadow-md shadow-indigo-500/20 transition-all shrink-0">Apply</button>
            </div>
            
            <div v-if="cartStore.coupon" class="p-3 bg-emerald-50 border border-emerald-200 rounded-xl text-xs flex items-center justify-between text-emerald-800 font-bold">
              <span>Coupon "{{ cartStore.coupon.code }}" active</span>
              <button @click="cartStore.removeCoupon" class="text-rose-600 hover:underline text-[10px]">Remove</button>
            </div>
          </div>

          <!-- Gift Card Input -->
          <div class="space-y-2 border-t border-slate-100 pt-3">
            <label class="block text-[11px] text-slate-500 font-bold">Gift Card Code:</label>
            <div class="flex gap-2">
              <input v-model="giftCardCode" type="text" placeholder="GIFT-XXXX" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs uppercase font-mono font-bold focus:outline-none focus:border-indigo-500" />
              <button @click="handleApplyGiftCard" class="px-4 py-2.5 bg-slate-900 text-white text-xs font-bold rounded-xl hover:bg-slate-800 transition-all shrink-0">Redeem</button>
            </div>
          </div>
        </div>

        <!-- Summary Totals -->
        <div class="bg-white p-6 rounded-3xl border border-slate-100 shadow-xs space-y-3.5">
          <h3 class="font-bold text-slate-900 text-sm border-b border-slate-100 pb-3">Order Summary</h3>

          <div class="flex justify-between text-xs text-slate-600">
            <span>Subtotal</span>
            <span class="font-bold">৳{{ cartStore.subtotal }}</span>
          </div>

          <div v-if="cartStore.discountAmount > 0" class="flex justify-between text-xs text-emerald-600 font-bold">
            <span>Coupon Discount</span>
            <span>-৳{{ cartStore.discountAmount }}</span>
          </div>

          <div v-if="cartStore.giftCardBalance > 0" class="flex justify-between text-xs text-indigo-600 font-bold">
            <span>Gift Card Credit</span>
            <span>-৳{{ cartStore.giftCardBalance }}</span>
          </div>

          <hr class="border-slate-100 my-2" />

          <div class="flex justify-between text-base font-black text-slate-900">
            <span>Grand Total</span>
            <span class="text-xl text-indigo-600">৳{{ cartStore.grandTotal }}</span>
          </div>

          <NuxtLink to="/checkout" class="block w-full py-4 bg-gradient-to-r from-indigo-600 to-indigo-700 hover:from-indigo-700 hover:to-indigo-800 text-white text-center font-black text-sm rounded-2xl shadow-lg shadow-indigo-500/25 transition-all mt-4">
            Proceed to Checkout →
          </NuxtLink>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { useCartStore } from '~/stores/cart';
import { useToastStore } from '~/stores/toast';

const cartStore = useCartStore();
const toastStore = useToastStore();

const couponCode = ref('');
const giftCardCode = ref('');

const freeShippingThreshold = 2000;
const freeShippingRemaining = computed(() => Math.max(0, freeShippingThreshold - cartStore.subtotal));
const freeShippingPercentage = computed(() => Math.min(100, Math.round((cartStore.subtotal / freeShippingThreshold) * 100)));

onMounted(() => {
  cartStore.fetchCart();
});

function resolveCartImage(item: any) {
  const url = item?.image_url;
  if (url && (url.startsWith('http://') || url.startsWith('https://'))) return url;
  if (url && url.startsWith('/storage')) return `http://localhost:8000${url}`;
  
  const name = item?.name?.toLowerCase() || '';
  if (name.includes('iphone') || name.includes('phone')) return 'https://images.unsplash.com/photo-1511707171634-5f897ff02aa9?auto=format&fit=crop&w=300&q=80';
  if (name.includes('nike') || name.includes('shoe')) return 'https://images.unsplash.com/photo-1542291026-7eec264c27ff?auto=format&fit=crop&w=300&q=80';
  if (name.includes('laravel') || name.includes('book')) return 'https://images.unsplash.com/photo-1516321318423-f06f85e504b3?auto=format&fit=crop&w=300&q=80';
  return 'https://images.unsplash.com/photo-1523275335684-37898b6baf30?auto=format&fit=crop&w=300&q=80';
}

async function handleApplyCoupon() {
  if (!couponCode.value.trim()) return;
  await cartStore.applyCoupon(couponCode.value);
  couponCode.value = '';
}

async function handleApplyGiftCard() {
  if (!giftCardCode.value.trim()) return;
  await cartStore.applyGiftCard(giftCardCode.value);
  giftCardCode.value = '';
}
</script>
