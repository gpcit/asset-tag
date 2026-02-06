<script setup lang="ts">
import { ref, onMounted } from 'vue';
import api from '@/services/api';
import NavBar from '@/components/NavBar.vue';

interface ActivityLog {
  id: number;
  user_name: string;
  user_role: string;
  action: string;
  module: string;
  record_id: number;
  old_data: Record<string, any>;
  new_data: Record<string, any>;
  created_at: string;
}

const logs = ref<ActivityLog[]>([]);
const currentPage = ref(1);
const lastPage = ref(1);
const loading = ref(false);

const fetchLogs = async (page = 1) => {
  loading.value = true;
  try {
    const response = await api.get(`/activity-logs?page=${page}`);
    logs.value = response.data.data;
    currentPage.value = response.data.current_page;
    lastPage.value = response.data.last_page;
  } catch (error) {
    console.error('Failed to fetch activity logs', error);
  } finally {
    loading.value = false;
  }
};

const prevPage = () => {
  if (currentPage.value > 1) fetchLogs(currentPage.value - 1);
};

const nextPage = () => {
  if (currentPage.value < lastPage.value) fetchLogs(currentPage.value + 1);
};

onMounted(() => fetchLogs());
</script>

<template>
  <NavBar />
  <div class="p-4">
    <h2 class="text-xl font-bold mb-4">Activity Logs</h2>

    <div v-if="loading">Loading...</div>

    <table v-else class="w-full border border-gray-300 border-collapse">
      <thead class="bg-gray-100">
        <tr>
          <th class="border p-2">User</th>
          <th class="border p-2">Role</th>
          <th class="border p-2">Action</th>
          <th class="border p-2">Module</th>
          <th class="border p-2">Record ID</th>
          <th class="border p-2">Old Data</th>
          <th class="border p-2">New Data</th>
          <th class="border p-2">Date</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="log in logs" :key="log.id">
          <td class="border p-2">{{ log.user_name }}</td>
          <td class="border p-2">{{ log.user_role }}</td>
          <td class="border p-2">{{ log.action }}</td>
          <td class="border p-2">{{ log.module }}</td>
          <td class="border p-2">{{ log.record_id }}</td>
          <td class="border p-2">
            <div v-for="(value, key) in log.old_data" :key="key">
              <strong>{{ key }}:</strong> {{ value }}
            </div>
          </td>
          <td class="border p-2">
            <div v-for="(value, key) in log.new_data" :key="key">
              <strong>{{ key }}:</strong> {{ value }}
            </div>
          </td>
          <td class="border p-2">{{ log.created_at }}</td>
        </tr>
      </tbody>
    </table>

    <div class="flex justify-between mt-4">
      <button
        class="px-4 py-2 bg-gray-300 rounded disabled:opacity-50"
        :disabled="currentPage === 1"
        @click="prevPage"
      >
        Prev
      </button>
      <span>Page {{ currentPage }} of {{ lastPage }}</span>
      <button
        class="px-4 py-2 bg-gray-300 rounded disabled:opacity-50"
        :disabled="currentPage === lastPage"
        @click="nextPage"
      >
        Next
      </button>
    </div>
  </div>
</template>

<style scoped>
div {
  word-break: break-word;
}
</style>
