<template>
  <div class="inline-flex items-center gap-1.5 bg-rose-500/10 text-rose-600 border border-rose-500/20 px-3 py-1 rounded-full text-xs font-mono font-bold">
    <span>⚡ Sale ends in:</span>
    <span class="bg-rose-600 text-white px-1.5 py-0.5 rounded text-[11px]">{{ hours }}h</span>
    <span>:</span>
    <span class="bg-rose-600 text-white px-1.5 py-0.5 rounded text-[11px]">{{ minutes }}m</span>
    <span>:</span>
    <span class="bg-rose-600 text-white px-1.5 py-0.5 rounded text-[11px]">{{ seconds }}s</span>
  </div>
</template>

<script setup lang="ts">
const props = withDefaults(defineProps<{
  endTime?: string;
}>(), {
  endTime: '',
});

const hours = ref('02');
const minutes = ref('45');
const seconds = ref('30');
let timer: ReturnType<typeof setInterval> | null = null;

onMounted(() => {
  let target = props.endTime ? new Date(props.endTime).getTime() : Date.now() + (1000 * 60 * 60 * 3);
  timer = setInterval(() => {
    const diff = Math.max(0, target - Date.now());
    hours.value = String(Math.floor(diff / (1000 * 60 * 60))).padStart(2, '0');
    minutes.value = String(Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60))).padStart(2, '0');
    seconds.value = String(Math.floor((diff % (1000 * 60)) / 1000)).padStart(2, '0');
  }, 1000);
});

onUnmounted(() => {
  if (timer) clearInterval(timer);
});
</script>
