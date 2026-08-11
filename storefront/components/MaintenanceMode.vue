<template>
  <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900 text-white p-6 text-center">
    <div class="max-w-md bg-slate-800 p-8 rounded-2xl shadow-2xl border border-slate-700">
      <div class="w-16 h-16 bg-indigo-600/20 text-indigo-400 rounded-full flex items-center justify-center mx-auto mb-4 text-3xl">
        🛠️
      </div>
      <h1 class="text-2xl font-bold mb-2">Scheduled Maintenance</h1>
      <p class="text-slate-400 text-sm mb-6">
        We are currently upgrading our store infrastructure to serve you better. Please check back shortly!
      </p>
      <div class="inline-flex items-center gap-2 px-4 py-2 bg-slate-700/50 rounded-lg text-xs font-mono text-indigo-300">
        <span class="w-2 h-2 rounded-full bg-amber-400 animate-ping"></span>
        Status: Maintenance Active
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
</script>
