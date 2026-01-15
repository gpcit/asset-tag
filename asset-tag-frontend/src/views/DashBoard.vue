<script setup lang="ts">
import { ref, onMounted } from 'vue'
import api from '@/services/api'
import NavBar from '@/components/NavBar.vue'

interface CompanySummary {
  company: string;
  asset_count: number;
  total_cost: number;
  categories: string;
}

const totalAssets = ref(0)
const totalCost = ref(0)
const byCompany = ref<CompanySummary[]>([])

const fetchDashboard = async () => {
  const { data } = await api.get('/dashboard/summary')
  totalAssets.value = data.totalAssets
  totalCost.value = data.totalCost
  byCompany.value = data.byCompany
}

// Format as Philippine Peso
const formatCurrency = (value: number) => {
  return value.toLocaleString('en-PH', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2
  })
}

onMounted(fetchDashboard)
</script>

<template>
  <NavBar/>
  <div class="p-6 space-y-6">
    <h1 class="text-2xl font-bold mb-4">Dashboard</h1>
    
    <!-- Summary Cards -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
      <div class="bg-emerald-600 text-white p-4 rounded shadow">
        <p class="font-medium">Total Assets</p>
        <p class="text-2xl font-bold">{{ totalAssets }}</p>
      </div>
      <div class="bg-blue-600 text-white p-4 rounded shadow">
        <p class="font-medium">Total Cost</p>
        <p class="text-2xl font-bold">₱{{ formatCurrency(totalCost) }}</p>
      </div>
    </div>
    
    <!-- Assets by Company Table -->
    <div class="overflow-x-auto bg-white rounded shadow p-4">
      <h2 class="text-lg font-semibold mb-2">Assets by Company</h2>
      <table class="min-w-full divide-y divide-gray-200 text-sm">
        <thead class="bg-gray-800 text-white">
          <tr>
            <th class="px-3 py-2 text-left">Company</th>
            <th class="px-3 py-2 text-center">Assets</th>
            <th class="px-3 py-2 text-right">Total Cost</th>
            <th class="px-3 py-2 text-left">Categories</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-200">
          <tr v-for="company in byCompany" :key="company.company" class="hover:bg-gray-100">
            <td class="px-3 py-2">{{ company.company }}</td>
            <td class="px-3 py-2 text-center">{{ company.asset_count }}</td>
            <td class="px-3 py-2 text-right">₱{{ formatCurrency(company.total_cost) }}</td>
            <td class="px-3 py-2">{{ company.categories }}</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>