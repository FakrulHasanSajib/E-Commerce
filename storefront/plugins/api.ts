import axios from 'axios';

export default defineNuxtPlugin((nuxtApp) => {
  const config = useRuntimeConfig();

  const api = axios.create({
    baseURL: config.public.apiBase,
    headers: {
      'Accept': 'application/json',
      'Content-Type': 'application/json',
    },
  });

  // Request Interceptor: Attach SSR-safe auth_token cookie & device fingerprint
  api.interceptors.request.use((req) => {
    const token = useCookie('auth_token').value;
    if (token) {
      req.headers.Authorization = `Bearer ${token}`;
    }

    const fingerprint = useCookie('device_fingerprint').value || 'fp_storefront_client';
    req.headers['X-Device-Fingerprint'] = fingerprint;

    return req;
  });

  // Response Interceptor: Handle 401 & 422 global errors
  api.interceptors.response.use(
    (response) => response,
    (error) => {
      if (error.response?.status === 401) {
        const tokenCookie = useCookie('auth_token');
        tokenCookie.value = null;

        if (import.meta.client) {
          const router = useRouter();
          router.push('/login');
        }
      }
      return Promise.reject(error);
    }
  );

  return {
    provide: {
      api,
    },
  };
});
