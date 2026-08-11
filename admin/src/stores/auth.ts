import { defineStore } from 'pinia';
import api from '../services/api';

export interface AdminUser {
  id: number;
  name: string;
  email: string;
  roles?: string[];
  permissions?: string[];
  two_factor_confirmed_at?: string | null;
}

export const useAdminAuthStore = defineStore('adminAuth', {
  state: () => ({
    token: localStorage.getItem('admin_token') || null,
    user: null as AdminUser | null,
    requires2FA: false,
    tempUserId: null as number | null,
    isLoading: false,
  }),
  getters: {
    isAuthenticated: (state) => !!state.token,
    hasPermission: (state) => (permission: string) => {
      if (state.user?.roles?.includes('super-admin')) return true;
      return state.user?.permissions?.includes(permission) ?? false;
    },
  },
  actions: {
    async login(credentials: { email: string; password: string }) {
      this.isLoading = true;
      try {
        const res = await api.post('/auth/login', credentials);
        if (res.data.requires_2fa) {
          this.requires2FA = true;
          this.tempUserId = res.data.user_id;
          return { requires_2fa: true };
        }

        if (res.data.token) {
          this.token = res.data.token;
          this.user = res.data.user;
          localStorage.setItem('admin_token', res.data.token);
        }
        return res.data;
      } finally {
        this.isLoading = false;
      }
    },

    async verify2FA(code: string) {
      this.isLoading = true;
      try {
        const res = await api.post('/admin/2fa/verify', {
          user_id: this.tempUserId,
          code,
        });

        if (res.data.token) {
          this.token = res.data.token;
          this.user = res.data.user;
          localStorage.setItem('admin_token', res.data.token);
          this.requires2FA = false;
          this.tempUserId = null;
        }
        return res.data;
      } finally {
        this.isLoading = false;
      }
    },

    async fetchCurrentUser() {
      if (!this.token) return null;
      try {
        const res = await api.get('/user');
        if (res.data?.user) {
          this.user = res.data.user;
        }
        return this.user;
      } catch (e) {
        this.logout();
        return null;
      }
    },

    logout() {
      if (this.token) {
        api.post('/auth/logout').catch(() => {});
      }
      this.token = null;
      this.user = null;
      this.requires2FA = false;
      this.tempUserId = null;
      localStorage.removeItem('admin_token');
    },
  },
});
