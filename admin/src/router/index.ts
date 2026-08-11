import { createRouter, createWebHistory } from 'vue-router';
import { useAdminAuthStore } from '../stores/auth';

const routes = [
  {
    path: '/login',
    name: 'login',
    component: () => import('../views/AdminLoginView.vue'),
    meta: { guestOnly: true },
  },
  {
    path: '/',
    component: () => import('../layouts/AdminLayout.vue'),
    meta: { requiresAuth: true },
    children: [
      {
        path: '',
        name: 'dashboard',
        component: () => import('../views/AdminDashboardView.vue'),
      },
      {
        path: 'settings',
        name: 'settings',
        component: () => import('../views/SettingsView.vue'),
        meta: { requiredPermission: 'manage-settings' },
      },
      {
        path: 'media',
        name: 'media',
        component: () => import('../views/MediaLibraryView.vue'),
      },
      {
        path: 'catalog',
        name: 'catalog',
        component: () => import('../views/CatalogManagementView.vue'),
        meta: { requiredPermission: 'manage-products' },
      },
      {
        path: 'flash-sales',
        name: 'flash-sales',
        component: () => import('../views/FlashSalesAdminView.vue'),
        meta: { requiredPermission: 'manage-products' },
      },
      // Phase 8 — Orders & Operations
      {
        path: 'orders',
        name: 'orders',
        component: () => import('../views/OrdersOperationsView.vue'),
        meta: { requiredPermission: 'manage-products' },
      },
      // Phase 9 — Marketing, SEO, Content, Shipping/Tax
      {
        path: 'marketing',
        name: 'marketing',
        component: () => import('../views/MarketingAdminView.vue'),
        meta: { requiredPermission: 'manage-products' },
      },
      {
        path: 'content',
        name: 'content',
        component: () => import('../views/ContentModerationView.vue'),
        meta: { requiredPermission: 'manage-products' },
      },
      {
        path: 'landing-builder',
        name: 'landing-builder',
        component: () => import('../views/LandingPageBuilderView.vue'),
        meta: { requiredPermission: 'manage-products' },
      },
      {
        path: 'seo',
        name: 'seo',
        component: () => import('../views/SeoAdminView.vue'),
        meta: { requiredPermission: 'manage-products' },
      },
      {
        path: 'shipping',
        name: 'shipping',
        component: () => import('../views/ShippingTaxView.vue'),
        meta: { requiredPermission: 'manage-products' },
      },
      // Phase 10 — Security, RBAC, Dashboard
      {
        path: 'security',
        name: 'security',
        component: () => import('../views/SecurityAdminView.vue'),
        meta: { requiredPermission: 'manage-products' },
      },
      {
        path: 'customers',
        name: 'customers',
        component: () => import('../views/CustomerListView.vue'),
        meta: { requiredPermission: 'manage-products' },
      },
      {
        path: 'inventory',
        name: 'inventory',
        component: () => import('../views/InventoryDashboardView.vue'),
        meta: { requiredPermission: 'manage-products' },
      },
      {
        path: 'campaigns',
        name: 'campaigns',
        component: () => import('../views/MarketingCampaignView.vue'),
        meta: { requiredPermission: 'manage-products' },
      },
      {
        path: '2fa-setup',
        name: '2fa-setup',
        component: () => import('../views/TwoFactorSetupView.vue'),
      },
      {
        path: 'health',
        name: 'health',
        component: () => import('../views/SystemHealthView.vue'),
        meta: { requiredPermission: 'manage-settings' },
      },
      {
        path: 'rbac',
        name: 'rbac',
        component: () => import('../views/RBACManagementView.vue'),
      },
    ],
  },
];

const router = createRouter({
  history: createWebHistory(),
  routes,
});

router.beforeEach(async (to, from, next) => {
  const authStore = useAdminAuthStore();

  if (authStore.token && !authStore.user) {
    await authStore.fetchCurrentUser();
  }

  if (to.meta.requiresAuth && !authStore.isAuthenticated) {
    return next({ name: 'login' });
  }

  if (to.meta.guestOnly && authStore.isAuthenticated) {
    return next({ name: 'dashboard' });
  }

  if (to.meta.requiredPermission) {
    const perm = to.meta.requiredPermission as string;
    if (!authStore.hasPermission(perm)) {
      return next({ name: 'dashboard' });
    }
  }

  next();
});

export default router;
