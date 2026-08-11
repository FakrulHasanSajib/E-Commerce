<template>
  <div class="space-y-6">
    <div class="bg-white p-6 rounded-2xl border border-slate-100 shadow-xs flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-black text-slate-900">Product Comparison</h1>
        <p class="text-slate-500 text-xs mt-1">Compare specifications, prices, and stock side-by-side.</p>
      </div>

      <button v-if="compareList.length" @click="clearAll" class="px-4 py-2 bg-rose-50 text-rose-600 font-bold text-xs rounded-xl hover:bg-rose-100">
        Clear All
      </button>
    </div>

    <!-- Empty State -->
    <div v-if="compareList.length === 0" class="bg-white p-12 rounded-2xl border border-slate-100 text-center space-y-4">
      <span class="text-4xl block">⚖️</span>
      <h3 class="font-bold text-slate-900 text-base">Your Comparison List is Empty</h3>
      <p class="text-slate-500 text-xs">Add products from product cards or product detail pages to compare specifications.</p>
      <NuxtLink to="/products" class="inline-block px-4 py-2 bg-indigo-600 text-white font-bold text-xs rounded-xl">
        Explore Products
      </NuxtLink>
    </div>

    <!-- Side-by-Side Comparison Table -->
    <div v-else class="bg-white rounded-2xl border border-slate-100 shadow-xs overflow-x-auto">
      <table class="w-full text-left text-xs border-collapse">
        <thead>
          <tr class="border-b border-slate-100 bg-slate-50">
            <th class="p-4 w-48 font-bold text-slate-700 uppercase">Specification</th>
            <th v-for="item in compareList" :key="item.id" class="p-4 min-w-64 font-bold text-slate-900">
              <div class="flex items-center justify-between">
                <span>{{ item.name }}</span>
                <button @click="removeItem(item.id)" class="text-rose-500 hover:text-rose-700 font-bold text-sm">✕</button>
              </div>
            </th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
          <tr>
            <td class="p-4 font-bold text-slate-500">Image</td>
            <td v-for="item in compareList" :key="item.id" class="p-4">
              <img :src="item.primary_image_url || '/placeholder-product.png'" class="w-24 h-24 object-cover rounded-xl bg-slate-100" />
            </td>
          </tr>
          <tr>
            <td class="p-4 font-bold text-slate-500">Price</td>
            <td v-for="item in compareList" :key="item.id" class="p-4 font-black text-slate-900 text-sm">
              ৳{{ item.sale_price || item.price }}
            </td>
          </tr>
          <tr>
            <td class="p-4 font-bold text-slate-500">Stock Status</td>
            <td v-for="item in compareList" :key="item.id" class="p-4 font-bold text-emerald-600">
              {{ item.stock_quantity > 0 ? 'In Stock' : 'Out of Stock' }}
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<script setup lang="ts">
const compareList = ref<any[]>([]);

onMounted(() => {
  if (import.meta.client) {
    const raw = localStorage.getItem('compare_products');
    if (raw) compareList.value = JSON.parse(raw);
  }
});

function removeItem(id: number) {
  compareList.value = compareList.value.filter(item => item.id !== id);
  if (import.meta.client) {
    localStorage.setItem('compare_products', JSON.stringify(compareList.value));
  }
}

function clearAll() {
  compareList.value = [];
  if (import.meta.client) {
    localStorage.removeItem('compare_products');
  }
}
</script>
