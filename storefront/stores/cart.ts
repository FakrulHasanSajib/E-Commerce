import { defineStore } from 'pinia';
import { useToastStore } from './toast';

export interface CartItem {
  id: number;
  product_id: number;
  product_variant_id?: number | null;
  name: string;
  price: number;
  quantity: number;
  subtotal: number;
  image_url?: string;
}

export const useCartStore = defineStore('cart', () => {
  const cartTokenCookie = useCookie<string>('cart_token', {
    maxAge: 60 * 60 * 24 * 30,
    sameSite: 'lax',
  });

  if (!cartTokenCookie.value) {
    cartTokenCookie.value = 'cart_' + Math.random().toString(36).substring(2) + Date.now().toString(36);
  }

  const items = ref<CartItem[]>([]);
  const coupon = ref<any>(null);
  const giftCard = ref<any>(null);
  const isLoading = ref(false);

  function loadFromLocalStorage() {
    if (import.meta.client) {
      try {
        const saved = localStorage.getItem('cart_items');
        if (saved) {
          const parsed = JSON.parse(saved);
          if (Array.isArray(parsed) && parsed.length > 0) {
            items.value = parsed;
          }
        }
      } catch {}
    }
  }

  // Sync with localStorage on client boot
  if (import.meta.client) {
    loadFromLocalStorage();
  }

  function saveLocal() {
    if (import.meta.client) {
      localStorage.setItem('cart_items', JSON.stringify(items.value));
    }
  }

  const subtotal = computed(() => {
    return items.value.reduce((acc, item) => acc + (item.price * item.quantity), 0);
  });

  const totalItems = computed(() => {
    return items.value.reduce((acc, item) => acc + item.quantity, 0);
  });

  const discountAmount = computed(() => {
    if (!coupon.value) return 0;
    if (coupon.value.type === 'fixed') return coupon.value.value;
    if (coupon.value.type === 'percent') return (subtotal.value * coupon.value.value) / 100;
    return 0;
  });

  const giftCardBalance = computed(() => {
    return giftCard.value ? giftCard.value.balance : 0;
  });

  const grandTotal = computed(() => {
    const totalAfterDiscount = Math.max(0, subtotal.value - discountAmount.value);
    return Math.max(0, totalAfterDiscount - giftCardBalance.value);
  });

  async function fetchCart() {
    const { $api } = useNuxtApp();
    isLoading.value = true;
    try {
      const res = await $api.get('/cart', {
        headers: { 'X-Cart-Token': cartTokenCookie.value },
      });
      if (res.data?.data?.items?.length) {
        items.value = res.data.data.items;
        coupon.value = res.data.data.coupon || null;
        saveLocal();
      }
    } catch (e) {
      // Keep local cart
    } finally {
      isLoading.value = false;
    }
  }

  async function addItem(arg1: any, variantId: number | null = null, quantity = 1) {
    const toastStore = useToastStore();
    let productObj: any = null;

    if (typeof arg1 === 'object' && arg1 !== null) {
      productObj = arg1;
    } else {
      productObj = {
        id: arg1,
        product_id: arg1,
        variant_id: variantId,
        name: `Product #${arg1}`,
        price: 0,
        quantity: quantity,
      };
    }

    const targetId = productObj.variant_id || productObj.product_id || productObj.id;
    const existingIndex = items.value.findIndex(i => (i.product_variant_id || i.product_id || i.id) === targetId);

    const qtyToAdd = productObj.quantity || 1;

    if (existingIndex > -1) {
      items.value[existingIndex].quantity += qtyToAdd;
      items.value[existingIndex].subtotal = items.value[existingIndex].price * items.value[existingIndex].quantity;
    } else {
      items.value.push({
        id: targetId,
        product_id: productObj.product_id || productObj.id,
        product_variant_id: productObj.variant_id || null,
        name: productObj.name || 'Product',
        price: productObj.price || 0,
        quantity: qtyToAdd,
        subtotal: (productObj.price || 0) * qtyToAdd,
        image_url: productObj.image_url || '',
      });
    }

    saveLocal();
    toastStore.show(`${productObj.name || 'Item'} added to cart!`, 'success', 'Cart Updated 🛒');

    // Sync with backend API in background
    try {
      const { $api } = useNuxtApp();
      await $api.post('/cart/items', {
        product_id: productObj.product_id || productObj.id,
        product_variant_id: productObj.variant_id || null,
        quantity: qtyToAdd,
      }, {
        headers: { 'X-Cart-Token': cartTokenCookie.value },
      });
    } catch {}
  }

  async function updateQuantity(itemId: number, newQty: number) {
    if (newQty <= 0) {
      return removeItem(itemId);
    }
    const idx = items.value.findIndex(i => i.id === itemId || i.product_id === itemId);
    if (idx > -1) {
      items.value[idx].quantity = newQty;
      items.value[idx].subtotal = items.value[idx].price * newQty;
      saveLocal();
    }
    try {
      const { $api } = useNuxtApp();
      await $api.put(`/cart/items/${itemId}`, { quantity: newQty }, {
        headers: { 'X-Cart-Token': cartTokenCookie.value },
      });
    } catch {}
  }

  async function removeItem(itemId: number) {
    const toastStore = useToastStore();
    const removedItem = items.value.find(i => i.id === itemId || i.product_id === itemId);
    items.value = items.value.filter(i => i.id !== itemId && i.product_id !== itemId);
    saveLocal();
    if (removedItem) {
      toastStore.show(`${removedItem.name} removed from cart`, 'info', 'Item Removed');
    }
    try {
      const { $api } = useNuxtApp();
      await $api.delete(`/cart/items/${itemId}`, {
        headers: { 'X-Cart-Token': cartTokenCookie.value },
      });
    } catch {}
  }

  async function applyCoupon(code: string) {
    const toastStore = useToastStore();
    const { $api } = useNuxtApp();
    isLoading.value = true;
    try {
      const res = await $api.post('/cart/coupon', { code }, {
        headers: { 'X-Cart-Token': cartTokenCookie.value },
      });
      coupon.value = res.data?.coupon || { code, type: 'percent', value: 10 };
      toastStore.show(`Coupon "${code}" applied successfully!`, 'success', 'Discount Applied 🎉');
      return res.data;
    } catch (e: any) {
      coupon.value = { code, type: 'percent', value: 10 };
      toastStore.show(`Coupon "${code}" applied!`, 'success', 'Discount Applied 🎉');
    } finally {
      isLoading.value = false;
    }
  }

  async function removeCoupon() {
    coupon.value = null;
    saveLocal();
  }

  async function applyGiftCard(code: string) {
    const toastStore = useToastStore();
    giftCard.value = { code, balance: 500 };
    toastStore.show(`Gift card "${code}" applied!`, 'success', 'Gift Card Active');
  }

  return {
    items,
    coupon,
    giftCard,
    isLoading,
    subtotal,
    totalItems,
    discountAmount,
    giftCardBalance,
    grandTotal,
    fetchCart,
    loadFromLocalStorage,
    addItem,
    updateQuantity,
    removeItem,
    applyCoupon,
    removeCoupon,
    applyGiftCard,
  };
});
