import { defineStore } from 'pinia';

export interface Toast {
  id: number;
  message: string;
  type: 'success' | 'error' | 'info' | 'warning';
  title?: string;
}

export const useToastStore = defineStore('toast', () => {
  const toasts = ref<Toast[]>([]);

  function show(message: string, type: 'success' | 'error' | 'info' | 'warning' = 'success', title?: string) {
    const id = Date.now() + Math.random();
    toasts.value.push({ id, message, type, title });
    setTimeout(() => {
      remove(id);
    }, 4000);
  }

  function remove(id: number) {
    toasts.value = toasts.value.filter(t => t.id !== id);
  }

  return { toasts, show, remove };
});
