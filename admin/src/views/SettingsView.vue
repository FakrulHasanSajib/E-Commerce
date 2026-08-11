<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="bg-slate-900 p-6 rounded-2xl border border-slate-800 flex items-center justify-between">
      <div>
        <h2 class="text-xl font-black text-white">System Settings Engine</h2>
        <p class="text-slate-400 text-xs mt-1">Configure system behavior, payment gateways, notifications, and branding.</p>
      </div>

      <button @click="saveSettings" :disabled="isSaving" class="px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs rounded-xl shadow-lg transition-all disabled:opacity-50 flex items-center gap-2">
        <span v-if="isSaving" class="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
        <span>{{ isSaving ? 'Saving...' : 'Save Settings Group' }}</span>
      </button>
    </div>

    <!-- Group Navigation Tabs -->
    <div class="flex items-center gap-2 overflow-x-auto pb-2 border-b border-slate-800 text-xs font-mono">
      <button
        v-for="group in groups"
        :key="group"
        @click="selectGroup(group)"
        :class="['px-4 py-2 rounded-xl uppercase tracking-wider font-bold transition-all shrink-0', activeGroup === group ? 'bg-indigo-600 text-white shadow-md' : 'bg-slate-900 text-slate-400 hover:bg-slate-800 hover:text-white']"
      >
        {{ group }}
      </button>
    </div>

    <!-- Dynamic Schema Form -->
    <div class="bg-slate-900 p-8 rounded-2xl border border-slate-800 space-y-6">
      <div v-if="isLoading" class="space-y-4">
        <div v-for="i in 4" :key="i" class="h-12 bg-slate-800 rounded-xl animate-spin"></div>
      </div>

      <div v-else-if="Object.keys(settingsForm).length > 0" class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div v-for="(val, key) in settingsForm" :key="key" class="space-y-1.5">
          <label class="block text-xs font-mono uppercase tracking-wider text-slate-300 font-bold">
            {{ formatKeyLabel(String(key)) }}
          </label>

          <!-- Boolean Toggle -->
          <div v-if="typeof val === 'boolean'" class="flex items-center gap-3 py-2">
            <button
              type="button"
              @click="settingsForm[key] = !settingsForm[key]"
              :class="['w-12 h-6 rounded-full p-1 transition-all', settingsForm[key] ? 'bg-indigo-600' : 'bg-slate-800']"
            >
              <div :class="['w-4 h-4 rounded-full bg-white transition-all', settingsForm[key] ? 'translate-x-6' : 'translate-x-0']"></div>
            </button>
            <span class="text-xs font-bold text-slate-300">{{ settingsForm[key] ? 'Enabled' : 'Disabled' }}</span>
          </div>

          <!-- Text / Number Input -->
          <input
            v-else
            v-model="settingsForm[key]"
            type="text"
            class="w-full px-4 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white focus:outline-hidden focus:border-indigo-500 font-mono"
          />
        </div>
      </div>

      <div v-else class="text-center py-12 text-slate-500 text-xs">
        No configurable settings found for this group.
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue';
import api from '../services/api';

const groups = ['general', 'theme', 'seo', 'payment', 'shipping', 'notifications', 'social', 'ecommerce', 'security', 'legal'];
const activeGroup = ref('general');
const settingsForm = ref<Record<string, any>>({});
const isLoading = ref(false);
const isSaving = ref(false);

const defaultGroupSettings: Record<string, Record<string, any>> = {
  general: { site_name: 'LaraShop BD', site_tagline: 'Leading E-Commerce Platform', support_phone: '+8801700000000', support_email: 'support@larashop.com', maintenance_mode: false },
  theme: { primary_color: '#4f46e5', dark_mode_default: true, font_family: 'Plus Jakarta Sans', enable_glassmorphism: true },
  seo: { meta_title: 'LaraShop - Online Shopping in Bangladesh', meta_description: 'Buy genuine phones, fashion, and lifestyle gadgets', google_analytics_id: 'G-XXXXXXX' },
  payment: { enable_cod: true, enable_bkash: true, enable_nagad: true, bkash_merchant_number: '01700000000', nagad_merchant_number: '01800000000' },
  shipping: { free_shipping_threshold: 2000, default_shipping_charge: 60, dhaka_delivery_time: '24-48 Hours' },
  notifications: { email_order_confirmation: true, sms_order_dispatch: true, admin_new_order_alert: true, mail_mailer: 'smtp', mail_host: 'smtp.mailtrap.io', mail_port: 587, whatsapp_notification_enabled: false },
  social: { facebook_url: 'https://facebook.com/larashop', instagram_url: 'https://instagram.com/larashop', youtube_url: 'https://youtube.com/larashop' },
  ecommerce: { allow_guest_checkout: true, inventory_low_stock_threshold: 5, enable_customer_reviews: true },
  security: { require_2fa_for_admins: true, password_min_length: 8, login_max_attempts: 5 },
  legal: { return_policy_days: 7, warranty_claim_enabled: true, terms_version: 'v2.1' },
};

onMounted(() => {
  fetchGroupSettings(activeGroup.value);
});

function selectGroup(group: string) {
  activeGroup.value = group;
  fetchGroupSettings(group);
}

function formatKeyLabel(key: string): string {
  return key.replace(/_/g, ' ').replace(/\./g, ' › ').toUpperCase();
}

function flattenObject(obj: Record<string, any>, prefix = ''): Record<string, any> {
  let result: Record<string, any> = {};
  for (const k in obj) {
    if (!Object.prototype.hasOwnProperty.call(obj, k)) continue;
    let val = obj[k];

    // Attempt to parse JSON string if string starts with { or [
    if (typeof val === 'string' && (val.startsWith('{') || val.startsWith('['))) {
      try { val = JSON.parse(val); } catch {}
    }

    const newKey = prefix ? `${prefix}.${k}` : k;
    if (val !== null && typeof val === 'object' && !Array.isArray(val)) {
      Object.assign(result, flattenObject(val, newKey));
    } else {
      let finalVal = val;
      if (finalVal === 'true' || finalVal === '1') finalVal = true;
      if (finalVal === 'false' || finalVal === '0') finalVal = false;
      result[newKey] = finalVal;
    }
  }
  return result;
}

async function fetchGroupSettings(group: string) {
  isLoading.value = true;
  settingsForm.value = {};
  try {
    const res = await api.get(`/admin/settings/${group}`);
    const rawData = res.data?.data || res.data;

    let dict: Record<string, any> = {};

    if (Array.isArray(rawData)) {
      rawData.forEach((item: any) => {
        if (item && item.key) {
          dict[item.key] = item.value;
        }
      });
    } else if (typeof rawData === 'object' && rawData !== null) {
      dict = { ...rawData };
    }

    // Flatten nested objects so no [object Object] ever appears
    let flattened = flattenObject(dict);

    settingsForm.value = flattened;
  } catch (e: any) {
    toastStore.show(`Failed to fetch settings for group "${group}". Please retry.`, 'error', 'Fetch Error');
  } finally {
    isLoading.value = false;
  }
}

import { useAdminToastStore } from '../stores/toast';

const toastStore = useAdminToastStore();

async function saveSettings() {
  isSaving.value = true;
  try {
    await api.patch('/admin/settings', {
      settings: settingsForm.value,
    });
    toastStore.show(`Settings group "${activeGroup.value.toUpperCase()}" saved successfully!`, 'success', 'Settings Saved');
  } catch (e: any) {
    toastStore.show(e.response?.data?.message || `Failed to save "${activeGroup.value.toUpperCase()}" settings.`, 'error', 'Save Failed');
  } finally {
    isSaving.value = false;
  }
}
</script>
