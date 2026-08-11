import { defineStore } from 'pinia';

export interface UserProfile {
  id: number;
  name: string;
  email: string;
  wallet_balance?: number;
  loyalty_points?: number;
  roles?: string[];
  permissions?: string[];
  two_factor_confirmed_at?: string | null;
}

export const useAuthStore = defineStore('auth', () => {
  const tokenCookie = useCookie<string | null>('auth_token', {
    maxAge: 60 * 60 * 24 * 7,
    sameSite: 'lax',
    secure: process.env.NODE_ENV === 'production',
  });

  const user = ref<UserProfile | null>(null);
  const isAuthenticated = computed(() => !!tokenCookie.value);
  const isLoading = ref(false);

  async function login(credentials: { email: string; password: string }) {
    const { $api } = useNuxtApp();
    isLoading.value = true;
    try {
      const res = await $api.post('/auth/login', credentials);
      if (res.data.token) {
        tokenCookie.value = res.data.token;
        user.value = res.data.user;
      }
      return res.data;
    } finally {
      isLoading.value = false;
    }
  }

  async function register(payload: { name: string; email: string; password: string; password_confirmation: string }) {
    const { $api } = useNuxtApp();
    isLoading.value = true;
    try {
      const res = await $api.post('/auth/register', payload);
      if (res.data.token) {
        tokenCookie.value = res.data.token;
        user.value = res.data.user;
      }
      return res.data;
    } finally {
      isLoading.value = false;
    }
  }

  async function fetchCurrentUser() {
    if (!tokenCookie.value) {
      user.value = null;
      return null;
    }

    const { $api } = useNuxtApp();
    try {
      const res = await $api.get('/user');
      if (res.data?.user) {
        user.value = res.data.user;
      }
      return user.value;
    } catch (e) {
      tokenCookie.value = null;
      user.value = null;
      return null;
    }
  }

  async function logout() {
    const { $api } = useNuxtApp();
    try {
      await $api.post('/auth/logout');
    } catch (e) {
      // Ignore network failures on logout
    } finally {
      tokenCookie.value = null;
      user.value = null;
      if (import.meta.client) {
        navigateTo('/login');
      }
    }
  }

  return {
    user,
    tokenCookie,
    isAuthenticated,
    isLoading,
    login,
    register,
    fetchCurrentUser,
    logout,
  };
});
