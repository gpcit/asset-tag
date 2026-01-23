<script setup lang="ts">
import { onMounted, onUnmounted } from 'vue'
import { initAutoLogout, clearAutoLogout } from '@/services/autoLogout'

onMounted(() => {
  console.log("App mounted, initializing auto-logout");
  initAutoLogout();
});

onUnmounted(() => {
  console.log("App unmounting, clearing auto-logout");
  clearAutoLogout();
});
</script>

<template>
  <router-view />
</template>

<style scoped></style>