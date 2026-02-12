<script setup lang="ts">
import { onMounted, onUnmounted } from 'vue'
import { initIdleLogout, clearIdleLogout } from '@/services/api' // ✅ correct functions

onMounted(() => {
  console.log("App mounted, initializing idle logout");
  initIdleLogout()
});

onUnmounted(() => {
  console.log("App unmounting, clearing idle logout");
  clearIdleLogout()
});
</script>

<template>
  <router-view />
</template>

<style scoped></style>