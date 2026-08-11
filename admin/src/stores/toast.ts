import { defineStore } from 'pinia';

export interface ToastMessage {
  id: number;
  message: string;
  type?: 'success' | 'error' | 'info' | 'warning';
  title?: string;
}

export const useAdminToastStore = defineStore('adminToast', {
  state: () => ({
    toasts: [] as ToastMessage[],
  }),
  actions: {
    show(message: string, type: 'success' | 'error' | 'info' | 'warning' = 'success', title = '') {
      const id = Date.now() + Math.random();
      this.toasts.push({ id, message, type, title });
      setTimeout(() => {
        this.remove(id);
      }, 4000);
    },
    remove(id: number) {
      this.toasts = this.toasts.filter(t => t.id !== id);
    },
  },
});
