<template>
  <div class="min-h-screen flex flex-col bg-slate-50 font-sans">
    <!-- Maintenance Mode Interstitial -->
    <MaintenanceMode v-if="settingsStore.settings.maintenance_mode" />

    <template v-else>
      <Header />
      <main class="flex-1 max-w-7xl w-full mx-auto px-4 md:px-8 pt-8 pb-16">
        <slot />
      </main>
      <Footer />
      <ToastNotification />
    </template>
  </div>
</template>

<script setup lang="ts">
import { useSettingsStore } from '~/stores/settings';
import { useAuthStore } from '~/stores/auth';

const settingsStore = useSettingsStore();
const authStore = useAuthStore();

const faviconUrl = computed(() => {
  const fav = settingsStore.settings.favicon || settingsStore.settings.site_favicon;
  if (!fav) return '/favicon.ico';
  if (fav.startsWith('http://') || fav.startsWith('https://')) return fav;
  return `http://localhost:8000${fav.startsWith('/') ? '' : '/'}${fav}`;
});

useHead({
  titleTemplate: (titleChunk) => {
    const siteName = settingsStore.settings.site_name || 'LaraShop';
    return titleChunk ? `${titleChunk} - ${siteName}` : siteName;
  },
  link: [
    {
      rel: 'icon',
      type: 'image/x-icon',
      href: faviconUrl,
    },
  ],
});

// SSR safe parallel initialization of public settings and authenticated user session
await useAsyncData('app-init', async () => {
  await Promise.all([
    settingsStore.fetchSettings(),
    authStore.fetchCurrentUser(),
  ]);
});

onMounted(() => {
  settingsStore.fetchSettings();
});
</script>
