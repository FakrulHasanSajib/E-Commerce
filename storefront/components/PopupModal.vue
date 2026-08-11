<template>
  <div v-if="isOpen && popup" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 p-4">
    <div class="bg-white p-8 rounded-3xl max-w-md w-full relative shadow-2xl space-y-4 text-center">
      <button @click="isOpen = false" class="absolute top-4 right-4 text-slate-400 hover:text-slate-600 font-bold text-base">✕</button>

      <div class="w-12 h-12 bg-indigo-100 text-indigo-600 rounded-2xl flex items-center justify-center text-2xl mx-auto font-bold">
        🎁
      </div>

      <h3 class="font-black text-slate-900 text-xl">{{ popup.title }}</h3>
      <p class="text-xs text-slate-500 leading-relaxed">{{ popup.content }}</p>

      <!-- Newsletter Subscribe Form -->
      <form @submit.prevent="handleSubscribe" class="space-y-3">
        <input v-model="email" type="email" required placeholder="Enter your email" class="w-full px-4 py-2.5 bg-slate-50 border rounded-xl text-xs" />
        <button type="submit" class="w-full py-3 bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs rounded-xl shadow-md">
          Claim Discount Now
        </button>
      </form>
    </div>
  </div>
</template>

<script setup lang="ts">
const { $api } = useNuxtApp();
const isOpen = ref(false);
const popup = ref<any>(null);
const email = ref('');

onMounted(async () => {
  try {
    const res = await $api.get('/popups');
    if (res.data?.data && res.data.data.length > 0) {
      popup.value = res.data.data[0];
      setTimeout(() => {
        isOpen.value = true;
      }, (popup.value.delay_seconds || 5) * 1000);
    }
  } catch (e) {
    // Ignore popup errors
  }
});

async function handleSubscribe() {
  try {
    await $api.post('/newsletter/subscribe', { email: email.value });
    alert('Thank you for subscribing!');
    isOpen.value = false;
  } catch (e: any) {
    alert(e.response?.data?.message || 'Subscription failed.');
  }
}
</script>
