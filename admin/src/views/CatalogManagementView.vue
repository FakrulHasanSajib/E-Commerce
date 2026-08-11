<template>
  <div class="space-y-6">
    <div class="bg-slate-900 p-6 rounded-2xl border border-slate-800 flex items-center justify-between">
      <div>
        <h2 class="text-xl font-black text-white">Catalog Management</h2>
        <p class="text-slate-400 text-xs mt-1">Manage products, variants matrix, category hierarchy, and brands.</p>
      </div>

      <div class="flex items-center gap-2">
        <button
          v-for="tab in ['products', 'categories', 'brands']"
          :key="tab"
          @click="activeTab = tab"
          :class="['px-4 py-2 rounded-xl text-xs font-bold uppercase tracking-wider transition-all', activeTab === tab ? 'bg-indigo-600 text-white shadow-md' : 'bg-slate-950 text-slate-400 hover:text-white']"
        >
          {{ tab }}
        </button>
      </div>
    </div>

    <!-- TAB 1: PRODUCTS MATRIX & CRUD -->
    <div v-if="activeTab === 'products'" class="space-y-6">
      <div class="flex items-center justify-between flex-wrap gap-4">
        <h3 class="font-bold text-white text-sm">Product Catalog List</h3>
        <div class="flex gap-2">
          <button @click="triggerCsvImport" class="px-3 py-2 bg-slate-800 hover:bg-slate-700 text-slate-200 font-bold text-xs rounded-xl border border-slate-700">
            📥 Import CSV
          </button>
          <button @click="exportProductsCsv" class="px-3 py-2 bg-slate-800 hover:bg-slate-700 text-slate-200 font-bold text-xs rounded-xl border border-slate-700">
            📤 Export CSV
          </button>
          <button @click="openProductForm()" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs rounded-xl shadow-md transition-all">
            + Create Product
          </button>
        </div>
      </div>

      <!-- Products Data Table -->
      <div class="bg-slate-900 rounded-2xl border border-slate-800 overflow-x-auto">
        <table class="w-full text-left text-xs border-collapse">
          <thead>
            <tr class="border-b border-slate-800 text-slate-400 font-mono uppercase">
              <th class="p-4">Product Name</th>
              <th class="p-4">SKU</th>
              <th class="p-4">Price (৳)</th>
              <th class="p-4">Stock</th>
              <th class="p-4">Status</th>
              <th class="p-4 text-right">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-800 text-slate-200">
            <tr v-for="prod in products" :key="prod.id" class="hover:bg-slate-800/50">
              <td class="p-4 font-bold text-white">{{ prod.name }}</td>
              <td class="p-4 font-mono text-slate-400">{{ prod.sku }}</td>
              <td class="p-4 font-mono font-bold text-emerald-400">৳{{ prod.price }}</td>
              <td class="p-4 font-mono">{{ prod.stock_quantity }}</td>
              <td class="p-4">
                <span :class="['px-2 py-0.5 rounded text-[10px] font-bold uppercase', prod.is_active ? 'bg-emerald-500/20 text-emerald-300' : 'bg-rose-500/20 text-rose-300']">
                  {{ prod.is_active ? 'Active' : 'Inactive' }}
                </span>
              </td>
              <td class="p-4 text-right space-x-2">
                <button @click="openProductForm(prod)" class="text-indigo-400 font-bold hover:underline">Edit</button>
                <button @click="deleteProduct(prod.id)" class="text-rose-400 font-bold hover:underline">Delete</button>
              </td>
            </tr>
          </tbody>
        </table>
        <div v-if="!products.length" class="text-center py-8 text-slate-500 text-xs">No products in catalog.</div>
      </div>
    </div>

    <!-- TAB 2: CATEGORY TREE MANAGEMENT -->
    <div v-if="activeTab === 'categories'" class="bg-slate-900 p-6 rounded-2xl border border-slate-800 space-y-4">
      <div class="flex items-center justify-between border-b border-slate-800 pb-4">
        <div>
          <h3 class="font-bold text-white text-sm">Category Hierarchy Tree</h3>
          <p class="text-slate-400 text-[11px] mt-0.5">Use ↑↓ buttons to reorder category display positions.</p>
        </div>
        <button @click="createCategory" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs rounded-xl shadow-xs">
          + Add Top Category
        </button>
      </div>

      <div class="space-y-2 text-xs">
        <div v-for="(cat, idx) in categories" :key="cat.id" class="p-3 bg-slate-950 rounded-xl border border-slate-800 flex items-center justify-between">
          <div class="flex items-center gap-3">
            <span class="text-slate-500 font-mono text-[10px]">#{{ idx + 1 }}</span>
            <span class="font-bold text-white">📁 {{ cat.name }} (Slug: {{ cat.slug }})</span>
          </div>
          <div class="flex items-center gap-2">
            <button @click="moveCategory(idx, -1)" :disabled="idx === 0" class="text-slate-400 hover:text-white px-1 disabled:opacity-30">↑</button>
            <button @click="moveCategory(idx, 1)" :disabled="idx === categories.length - 1" class="text-slate-400 hover:text-white px-1 disabled:opacity-30">↓</button>
            <button @click="deleteCategory(cat)" class="text-rose-400 font-bold hover:underline ml-2">Delete</button>
          </div>
        </div>
      </div>
    </div>

    <!-- TAB 3: BRAND MANAGEMENT -->
    <div v-if="activeTab === 'brands'" class="bg-slate-900 p-6 rounded-2xl border border-slate-800 space-y-4">
      <div class="flex items-center justify-between border-b border-slate-800 pb-4">
        <h3 class="font-bold text-white text-sm">Brand Directory</h3>
        <button @click="createBrand" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs rounded-xl shadow-xs">
          + Add Brand
        </button>
      </div>

      <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 text-xs">
        <div v-for="b in brands" :key="b.id" class="p-4 bg-slate-950 rounded-xl border border-slate-800 flex items-center justify-between">
          <span class="font-bold text-white">{{ b.name }}</span>
          <button @click="deleteBrand(b.id)" class="text-rose-400 font-bold hover:underline">Delete</button>
        </div>
      </div>
    </div>

    <!-- FULL TABBED PRODUCT FORM MODAL -->
    <div v-if="productForm.open" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/80 p-4">
      <div class="bg-slate-900 border border-slate-800 rounded-2xl p-6 max-w-2xl w-full space-y-5 shadow-2xl overflow-y-auto max-h-[90vh]">
        <div class="flex items-center justify-between border-b border-slate-800 pb-3">
          <h3 class="font-bold text-white text-base">{{ productForm.id ? 'Edit Product' : 'Create Product (Full Matrix Form)' }}</h3>
          <button @click="productForm.open = false" class="text-slate-400 hover:text-white font-bold text-xl">✕</button>
        </div>

        <!-- Form Tab Switcher -->
        <div class="flex gap-2 border-b border-slate-800 pb-3 text-xs font-bold overflow-x-auto">
          <button v-for="fTab in [
            { key: 'general', label: '1. General Info' },
            { key: 'pricing', label: '2. Pricing & Stock' },
            { key: 'variants', label: '3. Variants Matrix' },
            { key: 'images', label: '4. Gallery' },
            { key: 'seo', label: '5. SEO & Shipping' }
          ]" :key="fTab.key" @click="productFormTab = fTab.key"
            :class="['px-3 py-1.5 rounded-lg transition-all shrink-0', productFormTab === fTab.key ? 'bg-indigo-600 text-white' : 'bg-slate-950 text-slate-400 hover:text-white']">
            {{ fTab.label }}
          </button>
        </div>

        <!-- TAB 1: General Info -->
        <div v-if="productFormTab === 'general'" class="space-y-4 text-xs">
          <div>
            <label class="block text-slate-400 text-[10px] uppercase font-mono mb-1">Product Name *</label>
            <input v-model="productForm.name" type="text" placeholder="Product Title"
              class="w-full px-3 py-2 bg-slate-950 border border-slate-800 text-white rounded-xl focus:outline-none focus:border-indigo-500" />
          </div>
          <div>
            <label class="block text-slate-400 text-[10px] uppercase font-mono mb-1">Slug</label>
            <input v-model="productForm.slug" type="text" placeholder="product-slug"
              class="w-full px-3 py-2 bg-slate-950 border border-slate-800 text-white font-mono rounded-xl focus:outline-none focus:border-indigo-500" />
          </div>
          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-slate-400 text-[10px] uppercase font-mono mb-1">Category</label>
              <select v-model="productForm.category_id" class="w-full px-3 py-2 bg-slate-950 border border-slate-800 text-white rounded-xl">
                <option value="">Select Category</option>
                <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
              </select>
            </div>
            <div>
              <label class="block text-slate-400 text-[10px] uppercase font-mono mb-1">Brand</label>
              <select v-model="productForm.brand_id" class="w-full px-3 py-2 bg-slate-950 border border-slate-800 text-white rounded-xl">
                <option value="">Select Brand</option>
                <option v-for="b in brands" :key="b.id" :value="b.id">{{ b.name }}</option>
              </select>
            </div>
          </div>
          <div>
            <label class="block text-slate-400 text-[10px] uppercase font-mono mb-1">Description</label>
            <textarea v-model="productForm.description" rows="4" placeholder="Detailed description..."
              class="w-full px-3 py-2 bg-slate-950 border border-slate-800 text-white rounded-xl resize-none"></textarea>
          </div>
        </div>

        <!-- TAB 2: Pricing & Stock -->
        <div v-if="productFormTab === 'pricing'" class="space-y-4 text-xs">
          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-slate-400 text-[10px] uppercase font-mono mb-1">Regular Price (৳) *</label>
              <input v-model.number="productForm.price" type="number" step="0.01"
                class="w-full px-3 py-2 bg-slate-950 border border-slate-800 text-white rounded-xl" />
            </div>
            <div>
              <label class="block text-slate-400 text-[10px] uppercase font-mono mb-1">Sale Price (৳)</label>
              <input v-model.number="productForm.sale_price" type="number" step="0.01"
                class="w-full px-3 py-2 bg-slate-950 border border-slate-800 text-white rounded-xl" />
            </div>
            <div>
              <label class="block text-slate-400 text-[10px] uppercase font-mono mb-1">SKU *</label>
              <input v-model="productForm.sku" type="text" placeholder="PROD-001"
                class="w-full px-3 py-2 bg-slate-950 border border-slate-800 text-white font-mono rounded-xl" />
            </div>
            <div>
              <label class="block text-slate-400 text-[10px] uppercase font-mono mb-1">Stock Quantity *</label>
              <input v-model.number="productForm.stock_quantity" type="number"
                class="w-full px-3 py-2 bg-slate-950 border border-slate-800 text-white rounded-xl" />
            </div>
          </div>
        </div>

        <!-- TAB 3: Variants Matrix Builder -->
        <div v-if="productFormTab === 'variants'" class="space-y-4 text-xs">
          <div class="flex items-center justify-between">
            <span class="text-slate-400 text-[10px] uppercase font-mono">Variant Options (Size / Color / Spec)</span>
            <button type="button" @click="addVariantRow" class="text-indigo-400 font-bold hover:underline">+ Add Variant</button>
          </div>
          <div class="space-y-2">
            <div v-for="(v, idx) in productForm.variants" :key="idx" class="grid grid-cols-4 gap-2 items-center">
              <input v-model="v.sku" type="text" placeholder="SKU" class="px-2 py-1.5 bg-slate-950 border border-slate-800 text-white font-mono rounded-lg" />
              <input v-model="v.name" type="text" placeholder="e.g. Red / XL" class="px-2 py-1.5 bg-slate-950 border border-slate-800 text-white rounded-lg" />
              <input v-model.number="v.price" type="number" placeholder="Price ৳" class="px-2 py-1.5 bg-slate-950 border border-slate-800 text-white rounded-lg" />
              <div class="flex gap-2 items-center">
                <input v-model.number="v.stock_quantity" type="number" placeholder="Stock" class="flex-1 px-2 py-1.5 bg-slate-950 border border-slate-800 text-white rounded-lg" />
                <button @click="productForm.variants.splice(idx, 1)" class="text-rose-400 font-bold">✕</button>
              </div>
            </div>
            <div v-if="!productForm.variants.length" class="text-center py-4 text-slate-600 border border-dashed border-slate-800 rounded-xl">
              No variants added. Product will use master price and stock.
            </div>
          </div>
        </div>

        <!-- TAB 4: Images & Gallery -->
        <div v-if="productFormTab === 'images'" class="space-y-4 text-xs">
          <div>
            <label class="block text-slate-400 text-[10px] uppercase font-mono mb-1">Primary Image URL</label>
            <input v-model="productForm.primary_image_url" type="text" placeholder="https://..."
              class="w-full px-3 py-2 bg-slate-950 border border-slate-800 text-white font-mono rounded-xl" />
          </div>
        </div>

        <!-- TAB 5: SEO & Shipping -->
        <div v-if="productFormTab === 'seo'" class="space-y-4 text-xs">
          <div>
            <label class="block text-slate-400 text-[10px] uppercase font-mono mb-1">SEO Title</label>
            <input v-model="productForm.meta_title" type="text" placeholder="Meta title for search engines"
              class="w-full px-3 py-2 bg-slate-950 border border-slate-800 text-white rounded-xl" />
          </div>
          <div>
            <label class="block text-slate-400 text-[10px] uppercase font-mono mb-1">SEO Description</label>
            <textarea v-model="productForm.meta_description" rows="2" placeholder="Meta description..."
              class="w-full px-3 py-2 bg-slate-950 border border-slate-800 text-white rounded-xl resize-none"></textarea>
          </div>
          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-slate-400 text-[10px] uppercase font-mono mb-1">Weight (kg)</label>
              <input v-model.number="productForm.weight" type="number" step="0.01"
                class="w-full px-3 py-2 bg-slate-950 border border-slate-800 text-white rounded-xl" />
            </div>
            <div>
              <label class="block text-slate-400 text-[10px] uppercase font-mono mb-1">Dimensions (LxWxH cm)</label>
              <input v-model="productForm.dimensions" type="text" placeholder="10x5x2"
                class="w-full px-3 py-2 bg-slate-950 border border-slate-800 text-white font-mono rounded-xl" />
            </div>
          </div>
        </div>

        <!-- Modal Footer -->
        <div class="flex gap-3 justify-end pt-2 border-t border-slate-800">
          <button @click="productForm.open = false" class="px-5 py-2.5 text-xs font-bold text-slate-400 hover:text-white">Cancel</button>
          <button @click="saveProduct" :disabled="isSaving"
            class="px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white font-black text-xs rounded-xl disabled:opacity-50">
            {{ isSaving ? 'Saving...' : productForm.id ? 'Update Product' : 'Save Product' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue';
import api from '../services/api';

const activeTab = ref('products');
const productFormTab = ref('general');
const products = ref<any[]>([]);
const categories = ref<any[]>([]);
const brands = ref<any[]>([]);
const isSaving = ref(false);

const defaultProductForm = () => ({
  open: false, id: null as any,
  name: '', slug: '', description: '', category_id: '', brand_id: '',
  price: null as any, sale_price: null as any, sku: '', stock_quantity: 0,
  variants: [] as any[], primary_image_url: '', meta_title: '', meta_description: '',
  weight: null as any, dimensions: '',
});
const productForm = ref(defaultProductForm());

onMounted(() => { fetchData(); });

async function fetchData() {
  try { const p = await api.get('/admin/products'); products.value = p.data?.data || []; } catch {}
  try { const c = await api.get('/admin/categories'); categories.value = c.data?.data || []; } catch {}
  try { const b = await api.get('/admin/brands'); brands.value = b.data?.data || []; } catch {}
}

function openProductForm(prod?: any) {
  productFormTab.value = 'general';
  productForm.value = {
    open: true,
    id: prod?.id || null,
    name: prod?.name || '',
    slug: prod?.slug || '',
    description: prod?.description || '',
    category_id: prod?.category_id || '',
    brand_id: prod?.brand_id || '',
    price: prod?.price || null,
    sale_price: prod?.sale_price || null,
    sku: prod?.sku || '',
    stock_quantity: prod?.stock_quantity || 0,
    variants: prod?.variants?.map((v: any) => ({ ...v })) || [],
    primary_image_url: prod?.primary_image_url || '',
    meta_title: prod?.meta_title || '',
    meta_description: prod?.meta_description || '',
    weight: prod?.weight || null,
    dimensions: prod?.dimensions || '',
  };
}

function addVariantRow() {
  productForm.value.variants.push({ sku: '', name: '', price: null, stock_quantity: 0 });
}

async function saveProduct() {
  isSaving.value = true;
  try {
    const payload = { ...productForm.value };
    delete (payload as any).open;
    if (productForm.value.id) {
      await api.put(`/admin/products/${productForm.value.id}`, payload);
    } else {
      await api.post('/admin/products', payload);
    }
    productForm.value.open = false;
    await fetchData();
  } catch (e: any) {
    alert(e.response?.data?.message || 'Failed to save product.');
  } finally {
    isSaving.value = false;
  }
}

async function deleteProduct(id: number) {
  if (confirm('Soft delete product?')) { await api.delete(`/admin/products/${id}`); fetchData(); }
}

async function createCategory() {
  const name = prompt('Category Name:');
  if (name) { await api.post('/admin/categories', { name, slug: name.toLowerCase().replace(/\s+/g, '-') }); fetchData(); }
}

async function deleteCategory(cat: any) {
  if (confirm(`Delete category [${cat.name}]?`)) {
    try { await api.delete(`/admin/categories/${cat.id}`); fetchData(); }
    catch (e: any) { alert(e.response?.data?.message || 'Cannot delete category'); }
  }
}

async function createBrand() {
  const name = prompt('Brand Name:');
  if (name) { await api.post('/admin/brands', { name, slug: name.toLowerCase().replace(/\s+/g, '-'), is_active: true }); fetchData(); }
}

async function deleteBrand(id: number) {
  if (confirm('Delete brand?')) { await api.delete(`/admin/brands/${id}`); fetchData(); }
}

async function moveCategory(index: number, direction: number) {
  const target = index + direction;
  if (target < 0 || target >= categories.value.length) return;
  const temp = categories.value[index];
  categories.value[index] = categories.value[target];
  categories.value[target] = temp;
  try {
    await api.post('/admin/categories/reorder', {
      order: categories.value.map((c, i) => ({ id: c.id, position: i })),
    });
  } catch {}
}

function triggerCsvImport() {
  const input = document.createElement('input');
  input.type = 'file';
  input.accept = '.csv';
  input.onchange = async (e: any) => {
    const file = e.target.files[0];
    if (file) {
      const formData = new FormData();
      formData.append('file', file);
      try {
        await api.post('/admin/products/import-csv', formData);
        alert('CSV imported successfully!');
        fetchData();
      } catch (err: any) {
        alert(err.response?.data?.message || 'CSV import failed. Please check format.');
      }
    }
  };
  input.click();
}

function exportProductsCsv() {
  const headers = ['ID', 'Name', 'SKU', 'Price', 'Stock', 'Status'];
  const rows = products.value.map(p => [p.id, `"${p.name}"`, p.sku, p.price, p.stock_quantity, p.is_active ? 'Active' : 'Inactive']);
  const csvContent = [headers.join(','), ...rows.map(e => e.join(','))].join('\n');
  const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = `larashop-products-${new Date().toISOString().slice(0, 10)}.csv`;
  a.click();
  URL.revokeObjectURL(url);
}
</script>
