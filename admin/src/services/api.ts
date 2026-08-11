import axios from 'axios';

const api = axios.create({
  baseURL: 'http://localhost:8000/api',
  headers: {
    'Accept': 'application/json',
    'Content-Type': 'application/json',
  },
});

api.interceptors.request.use((config) => {
  const token = localStorage.getItem('admin_token');
  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }
  config.headers['X-Device-Fingerprint'] = 'fp_admin_panel_client';
  return config;
});

import { useAdminToastStore } from '../stores/toast';

api.interceptors.response.use(
  (response) => response,
  (error) => {
    const status = error.response?.status;
    const message = error.response?.data?.message || error.message || 'An unexpected API error occurred.';

    if (status === 401 && window.location.pathname !== '/login') {
      localStorage.removeItem('admin_token');
      window.location.href = '/login';
    } else {
      try {
        const toastStore = useAdminToastStore();
        toastStore.show(message, 'error', `API Error ${status ? '(' + status + ')' : ''}`);
      } catch (e) {
        console.error('API Error:', message);
      }
    }

    return Promise.reject(error);
  }
);

export default api;
