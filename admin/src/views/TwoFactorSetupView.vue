<template>
  <div class="space-y-6">
    <div class="bg-slate-900 p-6 rounded-2xl border border-slate-800">
      <h2 class="text-xl font-black text-white">Admin 2FA Setup Wizard</h2>
      <p class="text-slate-400 text-xs mt-1">Set up two-factor authentication for your admin account.</p>
    </div>

    <!-- Step Indicator -->
    <div class="flex items-center gap-3">
      <div v-for="(step, i) in steps" :key="i"
        :class="['flex items-center gap-2 px-3 py-1.5 rounded-full text-xs font-bold transition-all', currentStep === i ? 'bg-indigo-600 text-white' : currentStep > i ? 'bg-emerald-600/30 text-emerald-300' : 'bg-slate-800 text-slate-400']">
        <span>{{ currentStep > i ? '✓' : i + 1 }}</span>
        <span>{{ step }}</span>
      </div>
    </div>

    <!-- STEP 0: Enable 2FA — Show QR Code -->
    <div v-if="currentStep === 0" class="bg-slate-900 p-8 rounded-2xl border border-slate-800 space-y-6 max-w-md">
      <h3 class="font-bold text-white text-base">Step 1: Scan QR Code</h3>
      <p class="text-slate-400 text-xs">Open your authenticator app (Google Authenticator, Authy, etc.) and scan this QR code.</p>

      <div v-if="isLoading" class="w-48 h-48 bg-slate-800 rounded-2xl animate-pulse mx-auto"></div>
      <div v-else-if="qrCodeSvg" class="bg-white p-4 rounded-2xl w-48 h-48 mx-auto flex items-center justify-center"
        v-html="qrCodeSvg"></div>
      <div v-else class="text-center py-8">
        <button @click="enableTwoFactor" class="px-6 py-3 bg-indigo-600 text-white font-bold text-xs rounded-xl hover:bg-indigo-700">
          Generate QR Code
        </button>
      </div>

      <div v-if="secretKey" class="space-y-2">
        <div class="text-xs text-slate-400">Or enter this secret key manually:</div>
        <div class="px-4 py-2 bg-slate-950 border border-slate-700 rounded-xl font-mono text-amber-400 text-xs tracking-widest text-center select-all">
          {{ secretKey }}
        </div>
      </div>

      <button v-if="qrCodeSvg" @click="currentStep = 1"
        class="w-full py-3 bg-indigo-600 text-white font-bold text-xs rounded-xl hover:bg-indigo-700">
        I've scanned the QR code → Next
      </button>
    </div>

    <!-- STEP 1: Confirm TOTP Code -->
    <div v-if="currentStep === 1" class="bg-slate-900 p-8 rounded-2xl border border-slate-800 space-y-6 max-w-md">
      <h3 class="font-bold text-white text-base">Step 2: Confirm Setup</h3>
      <p class="text-slate-400 text-xs">Enter the 6-digit code from your authenticator app to confirm setup.</p>

      <div class="space-y-1">
        <label class="block text-[10px] font-mono uppercase text-slate-400">TOTP Code</label>
        <input v-model="confirmCode" type="text" maxlength="6" placeholder="000000"
          class="w-full px-4 py-3 bg-slate-950 border border-slate-800 text-white text-xl tracking-[1rem] font-mono text-center rounded-xl focus:outline-none focus:border-indigo-500" />
      </div>

      <button @click="confirmTwoFactor" :disabled="confirmCode.length !== 6"
        class="w-full py-3 bg-emerald-600 text-white font-bold text-xs rounded-xl hover:bg-emerald-700 disabled:opacity-50">
        Confirm & Activate 2FA
      </button>
    </div>

    <!-- STEP 2: Recovery Codes -->
    <div v-if="currentStep === 2" class="bg-slate-900 p-8 rounded-2xl border border-slate-800 space-y-6 max-w-lg">
      <div class="flex items-center gap-3">
        <div class="w-10 h-10 bg-emerald-600 rounded-xl flex items-center justify-center text-white font-black">✓</div>
        <div>
          <h3 class="font-bold text-white text-base">2FA Activated!</h3>
          <p class="text-slate-400 text-xs">Save your recovery codes — you'll need them if you lose your device.</p>
        </div>
      </div>

      <div class="bg-slate-950 border border-slate-800 rounded-2xl p-4 space-y-2">
        <div class="text-[10px] font-mono uppercase text-slate-500 mb-3">Recovery Codes (one-time use)</div>
        <div class="grid grid-cols-2 gap-2">
          <div v-for="code in recoveryCodes" :key="code"
            class="px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg font-mono text-amber-400 text-xs text-center tracking-wider">
            {{ code }}
          </div>
        </div>
      </div>

      <div class="flex gap-3">
        <button @click="downloadRecoveryCodes"
          class="flex-1 py-3 bg-amber-600 text-white font-bold text-xs rounded-xl hover:bg-amber-700">
          ↓ Download Recovery Codes
        </button>
        <button @click="printRecoveryCodes"
          class="flex-1 py-3 bg-slate-700 text-white font-bold text-xs rounded-xl hover:bg-slate-600">
          🖨 Print
        </button>
      </div>

      <div class="bg-rose-900/30 border border-rose-800/50 rounded-xl p-3 text-xs text-rose-300">
        ⚠ Store these codes safely. Each code can only be used once.
      </div>
    </div>

    <!-- Disable 2FA (if already enabled) -->
    <div v-if="twoFAEnabled" class="bg-slate-900 p-6 rounded-2xl border border-rose-900/40 space-y-4 max-w-md">
      <h3 class="font-bold text-rose-400 text-sm">Disable Two-Factor Authentication</h3>
      <p class="text-slate-400 text-xs">Disabling 2FA will reduce your account security. Enter your current TOTP code to confirm.</p>
      <input v-model="disableCode" type="text" maxlength="6" placeholder="Enter TOTP code"
        class="w-full px-4 py-2 bg-slate-950 border border-slate-800 text-white text-xs font-mono rounded-xl" />
      <button @click="disableTwoFactor"
        class="w-full py-3 bg-rose-600 text-white font-bold text-xs rounded-xl hover:bg-rose-700">
        Disable 2FA
      </button>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue';
import api from '../services/api';

const steps = ['Scan QR Code', 'Confirm Code', 'Save Recovery Codes'];
const currentStep = ref(0);
const isLoading = ref(false);
const qrCodeSvg = ref('');
const secretKey = ref('');
const confirmCode = ref('');
const recoveryCodes = ref<string[]>([]);
const twoFAEnabled = ref(false);
const disableCode = ref('');

onMounted(async () => {
  try {
    const res = await api.get('/admin/user');
    twoFAEnabled.value = !!res.data?.two_factor_confirmed_at;
    if (twoFAEnabled.value) currentStep.value = 2;
  } catch {}
});

async function enableTwoFactor() {
  isLoading.value = true;
  try {
    const res = await api.post('/admin/2fa/enable');
    const data = res.data;
    const qrUrl = data?.qr_url || (data?.qr_uri ? `https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=${encodeURIComponent(data.qr_uri)}` : '');
    qrCodeSvg.value = data?.svg || (qrUrl ? `<img src="${qrUrl}" alt="2FA QR Code" class="w-44 h-44 object-contain rounded-xl shadow-md mx-auto" />` : '');
    secretKey.value = data?.secret || data?.data?.secret || '';
  } catch (e: any) {
    alert(e.response?.data?.message || 'Failed to enable 2FA.');
  } finally {
    isLoading.value = false;
  }
}

async function confirmTwoFactor() {
  try {
    const res = await api.post('/admin/2fa/confirm', { code: confirmCode.value });
    recoveryCodes.value = res.data?.recovery_codes || [];
    currentStep.value = 2;
    twoFAEnabled.value = true;
  } catch (e: any) {
    alert(e.response?.data?.message || 'Invalid TOTP code. Please try again.');
  }
}

async function disableTwoFactor() {
  if (!confirm('Are you sure you want to disable 2FA?')) return;
  try {
    await api.post('/admin/2fa/disable', { code: disableCode.value });
    twoFAEnabled.value = false;
    currentStep.value = 0;
    qrCodeSvg.value = '';
    secretKey.value = '';
    disableCode.value = '';
    alert('2FA has been disabled.');
  } catch (e: any) {
    alert(e.response?.data?.message || 'Failed to disable 2FA.');
  }
}

function downloadRecoveryCodes() {
  const content = recoveryCodes.value.join('\n');
  const blob = new Blob([content], { type: 'text/plain' });
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = 'larashop-recovery-codes.txt';
  a.click();
  URL.revokeObjectURL(url);
}

function printRecoveryCodes() {
  const w = window.open('');
  if (w) {
    w.document.write(`<pre style="font-family:monospace;font-size:14px">${recoveryCodes.value.join('\n')}</pre>`);
    w.print();
    w.close();
  }
}
</script>
