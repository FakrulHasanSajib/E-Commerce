<template>
  <div class="fixed bottom-6 right-6 z-50 flex flex-col gap-3 max-w-sm w-full pointer-events-none">
    <transition-group name="toast">
      <div
        v-for="t in toastStore.toasts"
        :key="t.id"
        :class="[
          'pointer-events-auto p-4 rounded-2xl shadow-2xl border backdrop-blur-md flex items-start gap-3.5 text-xs transition-all duration-300 transform',
          t.type === 'success' ? 'bg-slate-900/95 border-emerald-500/50 text-white shadow-emerald-500/10' :
          t.type === 'error' ? 'bg-slate-900/95 border-rose-500/50 text-white shadow-rose-500/10' :
          t.type === 'warning' ? 'bg-slate-900/95 border-amber-500/50 text-white shadow-amber-500/10' :
          'bg-slate-900/95 border-indigo-500/50 text-white shadow-indigo-500/10'
        ]"
      >
        <!-- Icon -->
        <div class="w-8 h-8 rounded-xl flex items-center justify-center shrink-0 font-bold text-sm"
          :class="[
            t.type === 'success' ? 'bg-emerald-500/20 text-emerald-400' :
            t.type === 'error' ? 'bg-rose-500/20 text-rose-400' :
            t.type === 'warning' ? 'bg-amber-500/20 text-amber-400' :
            'bg-indigo-500/20 text-indigo-400'
          ]"
        >
          {{ t.type === 'success' ? '✓' : t.type === 'error' ? '✕' : t.type === 'warning' ? '⚠' : 'ℹ' }}
        </div>

        <!-- Message Body -->
        <div class="flex-1 min-w-0 pt-0.5">
          <h4 v-if="t.title" class="font-bold text-white text-xs mb-0.5">{{ t.title }}</h4>
          <p class="text-slate-300 font-medium leading-relaxed">{{ t.message }}</p>
        </div>

        <!-- Close Button -->
        <button
          @click="toastStore.remove(t.id)"
          class="text-slate-500 hover:text-white text-base leading-none p-1 transition-colors"
        >
          ×
        </button>
      </div>
    </transition-group>
  </div>
</template>

<script setup lang="ts">
import { useAdminToastStore } from '../stores/toast';

const toastStore = useAdminToastStore();
</script>

<style scoped>
.toast-enter-active,
.toast-leave-active {
  transition: all 0.35s cubic-bezier(0.16, 1, 0.3, 1);
}
.toast-enter-from {
  opacity: 0;
  transform: translateY(20px) scale(0.95);
}
.toast-leave-to {
  opacity: 0;
  transform: translateX(40px) scale(0.95);
}
</style>
