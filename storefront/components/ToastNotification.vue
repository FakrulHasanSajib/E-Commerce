<template>
  <div class="fixed bottom-6 right-6 z-50 flex flex-col gap-3 max-w-sm w-full pointer-events-none">
    <TransitionGroup name="toast">
      <div
        v-for="toast in toastStore.toasts"
        :key="toast.id"
        :class="[
          'pointer-events-auto p-4 rounded-2xl border shadow-xl flex items-center justify-between gap-3 text-xs font-bold transition-all',
          toast.type === 'success' ? 'bg-slate-900 border-emerald-500/40 text-white' :
          toast.type === 'error' ? 'bg-slate-900 border-rose-500/40 text-white' :
          toast.type === 'warning' ? 'bg-slate-900 border-amber-500/40 text-white' :
          'bg-slate-900 border-indigo-500/40 text-white'
        ]"
      >
        <div class="flex items-center gap-3">
          <div :class="[
            'w-8 h-8 rounded-xl flex items-center justify-center text-sm font-black shrink-0',
            toast.type === 'success' ? 'bg-emerald-500/20 text-emerald-400' :
            toast.type === 'error' ? 'bg-rose-500/20 text-rose-400' :
            toast.type === 'warning' ? 'bg-amber-500/20 text-amber-400' :
            'bg-indigo-500/20 text-indigo-400'
          ]">
            {{ toast.type === 'success' ? '✓' : toast.type === 'error' ? '✕' : toast.type === 'warning' ? '⚠' : 'ℹ' }}
          </div>
          <div>
            <div v-if="toast.title" class="font-black text-white text-xs mb-0.5">{{ toast.title }}</div>
            <div class="text-slate-300 font-medium leading-tight">{{ toast.message }}</div>
          </div>
        </div>

        <button @click="toastStore.remove(toast.id)" class="text-slate-400 hover:text-white font-bold p-1">✕</button>
      </div>
    </TransitionGroup>
  </div>
</template>

<script setup lang="ts">
import { useToastStore } from '~/stores/toast';

const toastStore = useToastStore();
</script>

<style scoped>
.toast-enter-active,
.toast-leave-active {
  transition: all 0.3s ease;
}
.toast-enter-from {
  opacity: 0;
  transform: translateY(20px) scale(0.95);
}
.toast-leave-to {
  opacity: 0;
  transform: translateX(100px);
}
</style>
