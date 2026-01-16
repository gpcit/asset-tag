<script setup lang="ts">
import { ref, onMounted } from 'vue'
import api from '@/services/api'
import NavBar from '@/components/NavBar.vue'

// Types
interface CompanySummary {
  company: string;
  asset_count: number;
  total_cost: number;
  categories: string;
}

// State
const loading = ref(true)
const error = ref<string | null>(null)

const totalAssets = ref(0)
const totalCost = ref(0)
const byCompany = ref<CompanySummary[]>([])

// Fetch dashboard data
const fetchDashboard = async () => {
  loading.value = true
  error.value = null

  try {
    const { data } = await api.get('/dashboard/summary') // make sure this route exists
    totalAssets.value = data.totalAssets
    totalCost.value = data.totalCost
    byCompany.value = data.byCompany
  } catch (err: any) {
    console.error('Failed to fetch dashboard:', err)
    error.value = err.response?.data?.message || 'Failed to load dashboard data.'
  } finally {
    loading.value = false
  }
}

// Format as Philippine Peso
const formatCurrency = (value: number) =>
  value.toLocaleString('en-PH', {
    style: 'currency',
    currency: 'PHP',
    minimumFractionDigits: 2,
    maximumFractionDigits: 2
  })

onMounted(() => {
  fetchDashboard()
})
</script>

<template>
  <NavBar />

  <div class="p-4 pt-20">
    <h1 class="text-2xl font-bold mb-4">Dashboard Summary</h1>

    <!-- Loading -->
    <div v-if="loading" class="text-center py-10 text-gray-500">
      Loading dashboard...
    </div>

    <!-- Error -->
    <div v-if="error" class="text-center py-10 text-red-500">
      {{ error }}
    </div>

    <!-- Content -->
    <div v-else>
      <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
        <div class="bg-emerald-100 p-4 rounded shadow">
          <p class="text-sm text-gray-700">Total Assets</p>
          <p class="text-xl font-bold">{{ totalAssets }}</p>
        </div>
        <div class="bg-emerald-100 p-4 rounded shadow">
          <p class="text-sm text-gray-700">Total Cost</p>
          <p class="text-xl font-bold">{{ formatCurrency(totalCost) }}</p>
        </div>
      </div>

      <h2 class="text-xl font-semibold mb-2">By Company</h2>
      <table class="min-w-full border border-gray-200 rounded shadow-sm text-sm">
        <thead class="bg-emerald-600 text-white">
          <tr>
            <th class="px-3 py-2 text-left">Company</th>
            <th class="px-3 py-2 text-right">Assets</th>
            <th class="px-3 py-2 text-right">Total Cost</th>
            <th class="px-3 py-2 text-left">Categories</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="item in byCompany" :key="item.company" class="hover:bg-emerald-50">
            <td class="px-3 py-1">{{ item.company }}</td>
            <td class="px-3 py-1 text-right">{{ item.asset_count }}</td>
            <td class="px-3 py-1 text-right">{{ formatCurrency(item.total_cost) }}</td>
            <td class="px-3 py-1">{{ item.categories }}</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>
