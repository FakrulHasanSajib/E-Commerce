<template>
  <div class="space-y-6 max-w-5xl mx-auto">
    <!-- Clean Page Title Header -->
    <div class="bg-white p-6 md:p-8 rounded-3xl border border-slate-200/80 shadow-xs flex items-center justify-between flex-wrap gap-4">
      <div>
        <div class="flex items-center gap-2 mb-1">
          <span class="px-3 py-0.5 bg-emerald-50 text-emerald-700 rounded-full text-[11px] font-bold tracking-wide border border-emerald-200/60 flex items-center gap-1">
            <span>🛡️</span>
            <span>256-Bit Encrypted Checkout</span>
          </span>
          <span v-if="!authStore.isAuthenticated" class="px-3 py-0.5 bg-indigo-50 text-indigo-700 rounded-full text-[11px] font-bold tracking-wide border border-indigo-200/60">
            Guest Mode (No Login Required)
          </span>
        </div>
        <h1 class="text-2xl font-bold text-slate-900 tracking-tight">Complete Your Order</h1>
        <p class="text-slate-500 text-xs mt-0.5">Enter your shipping details and select a payment method to place your order.</p>
      </div>

      <div class="text-right">
        <div class="text-[11px] text-slate-400 font-bold uppercase tracking-wider">Total Payable</div>
        <div class="text-2xl font-bold text-indigo-600 font-mono">৳{{ cartStore.grandTotal }}</div>
      </div>
    </div>

    <!-- Checkout Main Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">
      <!-- Left 2-Cols: Shipping Info & Payment Selection -->
      <div class="lg:col-span-2 space-y-6">
        <!-- 1. Customer Shipping Address Form -->
        <div class="bg-white p-6 md:p-8 rounded-3xl border border-slate-200/80 shadow-xs space-y-6">
          <div class="flex items-center justify-between border-b border-slate-100 pb-4">
            <h2 class="text-base font-bold text-slate-900 flex items-center gap-2.5">
              <span class="w-7 h-7 rounded-xl bg-indigo-600 text-white text-xs font-bold flex items-center justify-center">1</span>
              <span>Shipping & Contact Information</span>
            </h2>
          </div>

          <!-- Saved Address Picker (Logged-in users) -->
          <div v-if="savedAddresses.length" class="space-y-3">
            <label class="block text-xs font-bold text-slate-700">Select Saved Address:</label>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
              <div
                v-for="addr in savedAddresses"
                :key="addr.id"
                @click="selectSavedAddress(addr)"
                :class="['p-4 rounded-2xl border-2 transition-all cursor-pointer text-xs space-y-1', selectedSavedAddrId === addr.id ? 'border-indigo-600 bg-indigo-50/40 shadow-xs' : 'border-slate-200 hover:border-slate-300']"
              >
                <div class="font-bold text-slate-900">{{ addr.name || addr.address_line_1 }}</div>
                <div class="text-slate-500 text-[11px]">{{ addr.address_line_1 }}, {{ addr.district }}, {{ addr.division }}</div>
                <div class="text-indigo-600 font-mono text-[10px]">{{ addr.phone }}</div>
              </div>
            </div>
          </div>

          <!-- Direct Input Fields -->
          <div class="space-y-4 text-xs">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1.5">Full Name <span class="text-rose-500">*</span></label>
                <input v-model="form.name" type="text" placeholder="e.g. Tanvir Hossain" class="w-full px-4 py-2.5 bg-slate-50/50 border border-slate-200 rounded-xl font-medium focus:outline-none focus:border-indigo-500 focus:bg-white transition-all text-xs" />
              </div>
              <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1.5">Phone Number (bKash / Contact) <span class="text-rose-500">*</span></label>
                <input v-model="form.phone" type="text" placeholder="01700000000" class="w-full px-4 py-2.5 bg-slate-50/50 border border-slate-200 rounded-xl font-mono font-medium focus:outline-none focus:border-indigo-500 focus:bg-white transition-all text-xs" />
              </div>
            </div>

            <div>
              <label class="block text-xs font-semibold text-slate-700 mb-1.5">Email Address <span class="text-slate-400 font-normal">(Optional for order updates)</span></label>
              <input v-model="form.email" type="email" placeholder="tanvir@example.com" class="w-full px-4 py-2.5 bg-slate-50/50 border border-slate-200 rounded-xl font-medium focus:outline-none focus:border-indigo-500 focus:bg-white transition-all text-xs" />
            </div>

            <!-- Cascading Location Dropdowns (Division -> District -> Upazila) -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
              <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1.5">Division <span class="text-rose-500">*</span></label>
                <select v-model="form.division_id" @change="fetchDistricts" class="w-full px-3 py-2.5 bg-slate-50/50 border border-slate-200 rounded-xl font-medium focus:outline-none focus:border-indigo-500 text-xs">
                  <option value="">Select Division</option>
                  <option v-for="d in divisions" :key="d.id" :value="d.id">{{ d.name }}</option>
                </select>
              </div>

              <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1.5">District <span class="text-rose-500">*</span></label>
                <select v-model="form.district_id" @change="fetchUpazilas" :disabled="!form.division_id" class="w-full px-3 py-2.5 bg-slate-50/50 border border-slate-200 rounded-xl font-medium focus:outline-none focus:border-indigo-500 text-xs disabled:opacity-50">
                  <option value="">Select District</option>
                  <option v-for="d in districts" :key="d.id" :value="d.id">{{ d.name }}</option>
                </select>
              </div>

              <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1.5">Upazila / Thana <span class="text-rose-500">*</span></label>
                <select v-model="form.upazila_id" :disabled="!form.district_id" class="w-full px-3 py-2.5 bg-slate-50/50 border border-slate-200 rounded-xl font-medium focus:outline-none focus:border-indigo-500 text-xs disabled:opacity-50">
                  <option value="">Select Upazila</option>
                  <option v-for="u in upazilas" :key="u.id" :value="u.id">{{ u.name }}</option>
                </select>
              </div>
            </div>

            <div>
              <label class="block text-xs font-semibold text-slate-700 mb-1.5">Full Street Address <span class="text-rose-500">*</span></label>
              <textarea v-model="form.address" rows="2" placeholder="House no, road no, area landmarks..." class="w-full px-4 py-2.5 bg-slate-50/50 border border-slate-200 rounded-xl font-medium focus:outline-none focus:border-indigo-500 resize-none text-xs"></textarea>
            </div>
          </div>
        </div>

        <!-- 2. Payment Method Selector -->
        <div class="bg-white p-6 md:p-8 rounded-3xl border border-slate-200/80 shadow-xs space-y-6">
          <div class="flex items-center justify-between border-b border-slate-100 pb-4">
            <h2 class="text-base font-bold text-slate-900 flex items-center gap-2.5">
              <span class="w-7 h-7 rounded-xl bg-indigo-600 text-white text-xs font-bold flex items-center justify-center">2</span>
              <span>Payment Gateway</span>
            </h2>
          </div>

          <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
            <button
              v-for="pm in paymentMethods"
              :key="pm.key"
              type="button"
              @click="form.payment_method = pm.key"
              :class="['p-4 rounded-2xl border-2 transition-all text-left flex flex-col justify-between space-y-2', form.payment_method === pm.key ? 'border-indigo-600 bg-indigo-50/40 shadow-xs' : 'border-slate-200 hover:border-slate-300']"
            >
              <span class="text-2xl">{{ pm.icon }}</span>
              <div>
                <div class="font-bold text-slate-900 text-xs">{{ pm.title }}</div>
                <div class="text-[10px] text-slate-500 mt-0.5">{{ pm.subtitle }}</div>
              </div>
            </button>
          </div>

          <!-- Bank Transfer Manual Details Input -->
          <div v-if="form.payment_method === 'bank_transfer'" class="p-4 bg-indigo-50/50 border border-indigo-200 rounded-2xl space-y-3 text-xs">
            <div class="font-bold text-indigo-900">Bank Transfer Details:</div>
            <div class="text-slate-600 font-mono text-[11px]">Bank: City Bank BD • A/C: 1102938475001 • Branch: Gulshan</div>
            <div class="grid grid-cols-2 gap-3">
              <input v-model="form.bank_account_name" type="text" placeholder="Sender Account Name" class="px-3 py-2 bg-white border rounded-xl" />
              <input v-model="form.bank_trx_ref" type="text" placeholder="Transaction Ref / Slip No" class="px-3 py-2 bg-white border rounded-xl font-mono" />
            </div>
          </div>
        </div>
      </div>

      <!-- Right 1-Col: Order Summary & Place Order -->
      <div class="space-y-6">
        <div class="bg-white p-6 rounded-3xl border border-slate-200/80 shadow-xs space-y-4 sticky top-28">
          <h3 class="font-bold text-slate-900 text-sm border-b border-slate-100 pb-3">Order Items ({{ cartStore.items.length }})</h3>

          <div class="space-y-3 max-h-60 overflow-y-auto pr-1">
            <div v-for="item in cartStore.items" :key="item.id" class="flex items-center justify-between text-xs gap-3">
              <div class="flex items-center gap-2 min-w-0">
                <span class="w-5 h-5 bg-slate-100 rounded-md font-mono text-[10px] font-bold flex items-center justify-center shrink-0">{{ item.quantity }}x</span>
                <span class="font-bold text-slate-800 truncate">{{ item.name }}</span>
              </div>
              <span class="font-bold text-slate-900 shrink-0 font-mono">৳{{ item.price * item.quantity }}</span>
            </div>
          </div>

          <hr class="border-slate-100" />

          <div class="space-y-2 text-xs text-slate-600">
            <div class="flex justify-between">
              <span>Subtotal</span>
              <span class="font-bold text-slate-900 font-mono">৳{{ cartStore.subtotal }}</span>
            </div>
            <div v-if="cartStore.discountAmount > 0" class="flex justify-between text-emerald-600 font-bold">
              <span>Discount</span>
              <span class="font-mono">-৳{{ cartStore.discountAmount }}</span>
            </div>
            <div class="flex justify-between">
              <span>Delivery Fee</span>
              <span class="font-bold text-emerald-600">FREE</span>
            </div>
          </div>

          <hr class="border-slate-100" />

          <div class="flex justify-between text-base font-bold text-slate-900">
            <span>Grand Total</span>
            <span class="text-xl text-indigo-600 font-mono">৳{{ cartStore.grandTotal }}</span>
          </div>

          <button
            @click="placeOrder"
            :disabled="isSubmitting || !cartStore.items.length"
            class="w-full py-3.5 bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-sm rounded-2xl shadow-md shadow-indigo-500/20 transition-all disabled:opacity-50 flex items-center justify-center gap-2"
          >
            <span>🚀</span>
            <span>{{ isSubmitting ? 'Processing Order...' : 'Place Order Now' }}</span>
          </button>

          <div class="text-center text-[11px] text-slate-400 font-medium">
            🔒 Guest checkout enabled. No login required!
          </div>
        </div>
      </div>
    </div>

    <!-- Order Success Confirmation Modal -->
    <div v-if="completedOrder" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/70 p-4 backdrop-blur-sm">
      <div class="bg-white rounded-3xl p-8 max-w-md w-full space-y-6 text-center shadow-2xl border border-slate-100">
        <div class="w-16 h-16 bg-emerald-100 text-emerald-600 rounded-3xl flex items-center justify-center text-3xl font-bold mx-auto">
          ✓
        </div>

        <div class="space-y-1">
          <span class="px-3 py-1 bg-emerald-100 text-emerald-800 rounded-full text-[10px] font-bold uppercase tracking-widest">
            Order Confirmed 🎉
          </span>
          <h2 class="text-xl font-bold text-slate-900 pt-2">Thank You for Your Order!</h2>
          <p class="text-slate-500 text-xs">Your order has been placed and is now being processed.</p>
        </div>

        <div class="bg-slate-50 p-4 rounded-2xl border border-slate-100 space-y-2 text-xs text-left font-medium">
          <div class="flex justify-between">
            <span class="text-slate-500">Order ID:</span>
            <span class="font-mono font-bold text-indigo-600">#{{ completedOrder.id || 'ORD-' + Math.floor(1000 + Math.random() * 9000) }}</span>
          </div>
          <div class="flex justify-between">
            <span class="text-slate-500">Recipient Name:</span>
            <span class="font-bold text-slate-800">{{ completedOrder.shipping_name }}</span>
          </div>
          <div class="flex justify-between">
            <span class="text-slate-500">Contact Phone:</span>
            <span class="font-mono font-bold text-slate-800">{{ completedOrder.shipping_phone }}</span>
          </div>
          <div class="flex justify-between">
            <span class="text-slate-500">Payment Method:</span>
            <span class="font-bold text-emerald-600 uppercase">{{ completedOrder.payment_method }}</span>
          </div>
        </div>

        <div class="flex gap-3">
          <NuxtLink to="/products" class="flex-1 py-3 bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs rounded-2xl transition-all shadow-md">
            Continue Shopping
          </NuxtLink>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { useCartStore } from '~/stores/cart';
import { useToastStore } from '~/stores/toast';
import { useAuthStore } from '~/stores/auth';

const { $api } = useNuxtApp();
const cartStore = useCartStore();
const toastStore = useToastStore();
const authStore = useAuthStore();

const isSubmitting = ref(false);
const completedOrder = ref<any>(null);
const savedAddresses = ref<any[]>([]);
const selectedSavedAddrId = ref<number | null>(null);

const divisions = ref<any[]>([]);
const districts = ref<any[]>([]);
const upazilas = ref<any[]>([]);

const form = ref({
  name: '',
  phone: '',
  email: '',
  division_id: '',
  district_id: '',
  upazila_id: '',
  address: '',
  payment_method: 'cod',
  bank_account_name: '',
  bank_trx_ref: '',
});

const paymentMethods = [
  { key: 'cod', title: 'Cash on Delivery', subtitle: 'Pay when delivered', icon: '💵' },
  { key: 'bkash', title: 'bKash Mobile', subtitle: 'Instant bKash pay', icon: '📱' },
  { key: 'nagad', title: 'Nagad Mobile', subtitle: 'Instant Nagad pay', icon: '📲' },
  { key: 'card', title: 'Credit/Debit Card', subtitle: 'Visa / Mastercard', icon: '💳' },
  { key: 'wallet', title: 'LaraShop Wallet', subtitle: 'Instant balance pay', icon: '👛' },
  { key: 'bank_transfer', title: 'Bank Transfer', subtitle: 'Direct bank deposit', icon: '🏦' },
];

onMounted(async () => {
  cartStore.loadFromLocalStorage();
  fetchDivisions();
  if (authStore.isAuthenticated) {
    fetchSavedAddresses();
  }
});

const bdLocationDataset: Record<string, { districts: Record<string, string[]> }> = {
  'Dhaka': {
    districts: {
      'Dhaka': ['Gulshan', 'Dhanmondi', 'Uttara', 'Mirpur', 'Mohammadpur', 'Banani', 'Motijheel', 'Tejgaon', 'Savar', 'Dhamrai', 'Keraniganj', 'Jatrabari', 'Badda', 'Khilkhet'],
      'Gazipur': ['Gazipur Sadar', 'Kaliakair', 'Kapasia', 'Sreepur', 'Kaliganj'],
      'Narayanganj': ['Narayanganj Sadar', 'Araihazar', 'Bandar', 'Rupganj', 'Sonargaon'],
      'Tangail': ['Tangail Sadar', 'Basail', 'Bhuapur', 'Delduar', 'Ghatail', 'Gopalpur', 'Kalihati', 'Madhupur', 'Mirzapur', 'Nagarpur', 'Sakhipur'],
      'Faridpur': ['Faridpur Sadar', 'Alfadanga', 'Bhanga', 'Boalmari', 'Charbhadrasan', 'Madhukhali', 'Nagarkanda', 'Sadarpur', 'Saltha'],
      'Manikganj': ['Manikganj Sadar', 'Singair', 'Saturia', 'Shibalaya', 'Harirampur', 'Ghior', 'Daulatpur'],
      'Munshiganj': ['Munshiganj Sadar', 'Gazaria', 'Tongibari', 'Sreenagar', 'Lohajang', 'Sirajdikhan'],
      'Narsingdi': ['Narsingdi Sadar', 'Belabo', 'Monohardi', 'Palash', 'Raipura', 'Shibpur'],
      'Rajbari': ['Rajbari Sadar', 'Baliakandi', 'Goalandaghat', 'Pangsha', 'Zanjira'],
      'Gopalganj': ['Gopalganj Sadar', 'Kashiani', 'Kotalipara', 'Muksudpur', 'Tungipara'],
      'Madaripur': ['Madaripur Sadar', 'Kalkini', 'Rajoir', 'Shibchar'],
      'Kishoreganj': ['Kishoreganj Sadar', 'Bhairab', 'Bajitpur', 'Hossainpur', 'Itna', 'Karimganj', 'Katiadi', 'Kuliarchar', 'Mithamain', 'Nikli', 'Pakundia', 'Tarail'],
    }
  },
  'Chattogram': {
    districts: {
      'Chattogram': ['Kotwali', 'Panchlaish', 'Halishahar', 'Agrabad', 'Hathazari', 'Patiya', 'Raozan', 'Sitakunda', 'Anwara', 'Banshkhali', 'Boalkhali', 'Chandanaish', 'Fatikchhari', 'Lohagara', 'Mirsharai', 'Sandeep'],
      'Cox\'s Bazar': ['Cox\'s Bazar Sadar', 'Chakaria', 'Maheshkhali', 'Ramu', 'Teknaf', 'Ukhia', 'Pekua', 'Kutubdia'],
      'Cumilla': ['Cumilla Sadar', 'Barura', 'Brahmanpara', 'Burichang', 'Chandina', 'Chuddagram', 'Daudkandi', 'Debidwar', 'Homna', 'Laksam', 'Muradnagar', 'Nangalkot', 'Titas'],
      'Feni': ['Feni Sadar', 'Chhagalnaiya', 'Daganbhuiyan', 'Parshuram', 'Fulgazi', 'Sonagazi'],
      'Noakhali': ['Noakhali Sadar', 'Begumganj', 'Chatkhil', 'Companiganj', 'Hatiya', 'Senbagh', 'Subarnachar', 'Kabirhat'],
      'Brahmanbaria': ['Brahmanbaria Sadar', 'Ashuganj', 'Bancharampur', 'Kasba', 'Nabinagar', 'Nasirnagar', 'Sarail'],
      'Chandpur': ['Chandpur Sadar', 'Faridganj', 'Haimchar', 'Hajiganj', 'Kachua', 'Matlab North', 'Matlab South', 'Shahrasti'],
    }
  },
  'Rajshahi': {
    districts: {
      'Rajshahi': ['Boalia', 'Rajpara', 'Motihar', 'Shah Mokdum', 'Paba', 'Godagari', 'Tanore', 'Mohanpur', 'Bagmara', 'Durgapur', 'Puthia', 'Charghat', 'Bagha'],
      'Bogura': ['Bogura Sadar', 'Adamdighi', 'Dhunat', 'Dhupchanchia', 'Gabtali', 'Kahaloo', 'Nandigram', 'Sariakandi', 'Shajahanpur', 'Sherpur', 'Shibganj', 'Sonatala'],
      'Pabna': ['Pabna Sadar', 'Atgharia', 'Bera', 'Bhangura', 'Chatmohar', 'Faridpur', 'Ishwardi', 'Santhia', 'Sujanagar'],
      'Sirajganj': ['Sirajganj Sadar', 'Belkuchi', 'Chauhali', 'Kamarkhanda', 'Kazipur', 'Rayganj', 'Shahjadpur', 'Tarash', 'Ullahpara'],
    }
  },
  'Khulna': {
    districts: {
      'Khulna': ['Khulna Sadar', 'Sonadanga', 'Khalishpur', 'Daulatpur', 'Khan Jahan Ali', 'Batiaghata', 'Dacope', 'Dumuria', 'Dighalia', 'Koyra', 'Paikgachha', 'Phultala', 'Rupsha', 'Terokhada'],
      'Jashore': ['Jashore Sadar', 'Abhaynagar', 'Bagherpara', 'Chaugachha', 'Jhikargachha', 'Keshabpur', 'Manirampur', 'Sharsha'],
      'Kushtia': ['Kushtia Sadar', 'Kumarkhali', 'Daulatpur', 'Mirpur', 'Bheramara', 'Khoksa'],
    }
  },
  'Sylhet': {
    districts: {
      'Sylhet': ['Sylhet Sadar', 'Beanibazar', 'Bishwanath', 'Companiganj', 'Fenchuganj', 'Golapganj', 'Gowainghat', 'Jaintiapur', 'Kanaighat', 'Zakiganj'],
      'Moulvibazar': ['Moulvibazar Sadar', 'Barlekha', 'Juri', 'Kamalganj', 'Kulaura', 'Rajnagar', 'Sreemangal'],
      'Habiganj': ['Habiganj Sadar', 'Ajmiriganj', 'Baniachang', 'Bahubal', 'Chunarughat', 'Madhabpur', 'Nabiganj'],
    }
  },
  'Barishal': {
    districts: {
      'Barishal': ['Barishal Sadar', 'Agailjhara', 'Babuganj', 'Bakerganj', 'Banaripara', 'Gaurnadi', 'Hizla', 'Mehendiganj', 'Muladi', 'Wazirpur'],
      'Bhola': ['Bhola Sadar', 'Burhanuddin', 'Char Fasson', 'Daulatkhan', 'Lalmohan', 'Manpura', 'Tazumuddin'],
    }
  },
  'Rangpur': {
    districts: {
      'Rangpur': ['Rangpur Sadar', 'Badarganj', 'Gangachhara', 'Kaunia', 'Mithapukur', 'Pirgachha', 'Pirganj', 'Taraganj'],
      'Dinajpur': ['Dinajpur Sadar', 'Birampur', 'Birganj', 'Biral', 'Bochaganj', 'Chirirbandar', 'Phulbari', 'Ghoraghat', 'Hakimpur', 'Kaharole', 'Khansama', 'Nawabganj', 'Parbatipur'],
    }
  },
  'Mymensingh': {
    districts: {
      'Mymensingh': ['Mymensingh Sadar', 'Bhaluka', 'Dhobaura', 'Fulbaria', 'Gaffargaon', 'Gauripur', 'Haluaghat', 'Ishwarganj', 'Muktagachha', 'Nandail', 'Phulpur', 'Trishal'],
      'Jamalpur': ['Jamalpur Sadar', 'Baksiganj', 'Dewanganj', 'Isampur', 'Madarganj', 'Melandaha', 'Sarishabari'],
    }
  }
};

async function fetchDivisions() {
  const divList = Object.keys(bdLocationDataset).map((name, i) => ({ id: name, name }));
  divisions.value = divList;
}

function fetchDistricts() {
  districts.value = [];
  upazilas.value = [];
  form.value.district_id = '';
  form.value.upazila_id = '';

  const divName = form.value.division_id;
  if (!divName || !bdLocationDataset[divName]) return;

  const distNames = Object.keys(bdLocationDataset[divName].districts);
  districts.value = distNames.map(name => ({ id: name, name }));
}

function fetchUpazilas() {
  upazilas.value = [];
  form.value.upazila_id = '';

  const divName = form.value.division_id;
  const distName = form.value.district_id;
  if (!divName || !distName || !bdLocationDataset[divName]?.districts[distName]) return;

  const upaNames = bdLocationDataset[divName].districts[distName];
  upazilas.value = upaNames.map(name => ({ id: name, name }));
}

async function fetchSavedAddresses() {
  try {
    const res = await $api.get('/customer/addresses');
    savedAddresses.value = res.data?.data || [];
  } catch {}
}

function selectSavedAddress(addr: any) {
  selectedSavedAddrId.value = addr.id;
  form.value.name = addr.name || form.value.name;
  form.value.phone = addr.phone || form.value.phone;
  form.value.address = addr.address_line_1 || form.value.address;
}

async function placeOrder() {
  if (!form.value.name || !form.value.phone || !form.value.address || !form.value.division_id || !form.value.district_id) {
    toastStore.show('Please select your Division, District, and enter full address.', 'error', 'Missing Information');
    return;
  }

  isSubmitting.value = true;
  try {
    const payload = {
      shipping_name: form.value.name,
      shipping_phone: form.value.phone,
      shipping_email: form.value.email,
      shipping_address: form.value.address,
      payment_method: form.value.payment_method,
      bank_account_name: form.value.bank_account_name,
      bank_trx_ref: form.value.bank_trx_ref,
      items: cartStore.items,
    };

    const res = await $api.post('/orders', payload);
    const orderData = res.data?.order || res.data?.data || { id: Math.floor(1000 + Math.random() * 9000), ...payload };
    
    completedOrder.value = orderData;
    toastStore.show('Your order has been placed successfully!', 'success', 'Order Confirmed 🎉');
    
    cartStore.items = [];
    if (import.meta.client) localStorage.removeItem('cart_items');
  } catch (e: any) {
    const errorMsg = e.response?.data?.message || e.response?.data?.checkout?.[0] || 'Failed to place order. Please check your information.';
    toastStore.show(errorMsg, 'error', 'Order Failed ❌');
  } finally {
    isSubmitting.value = false;
  }
}
</script>
