<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import api from '@/services/api'
import NavBar from '@/components/NavBar.vue'
import PieChart from '@/components/PieChart.vue' // Adjust path accordingly
import type { ChartData, ChartOptions } from 'chart.js'

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

// Prepare Pie Chart data and options (reactive)
const pieData = computed<ChartData<'pie', number[], string>>(() => ({
  labels: byCompany.value.map(item => item.company),
  datasets: [
    {
      data: byCompany.value.map(item => item.asset_count),
      backgroundColor: [
        '#10B981', '#34D399', '#6EE7B7', '#A7F3D0', '#D1FAE5', 
        '#059669', '#047857', '#065F46', '#064E3B', '#022C22'
      ].slice(0, byCompany.value.length),
      hoverOffset: 10,
    },
  ],
}))

const pieOptions: ChartOptions<'pie'> = {
  responsive: true,
  plugins: {
    legend: {
      position: 'right',
      labels: { color: '#065F46' } // dark green text
    },
    tooltip: { enabled: true },
  },
}

onMounted(() => {
  fetchDashboard()
})
</script>
<template>
  <NavBar />

  <div class="p-4 pt-5">
    <h1 class="text-2xl font-bold mb-4">Dashboard Summary</h1>

    <div v-if="loading" class="text-center py-10 text-gray-500">
      Loading dashboard...
    </div>

    <div v-if="error" class="text-center py-10 text-red-500">
      {{ error }}
    </div>

    <div v-else>
      <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
        <div class="bg-emerald-100 p-4 rounded shadow">
          <p class="text-sm text-gray-700">Total Assets</p>
          <p class="text-xl font-bold">{{ totalAssets }}</p>
        </div>
        <div class="bg-emerald-100 p-4 rounded shadow">
          <p class="text-sm text-gray-700">Total Cost</p>
          <p class="text-xl font-bold">₱{{ formatCurrency(totalCost) }}</p>
        </div>
      </div>

  <div class="p-6 pt-8 max-w-7xl mx-auto">
    <!-- ... your summary cards and other parts stay above ... -->

    <!-- By Company Section – Redesigned -->
    <section class="bg-white rounded-xl shadow-md border border-gray-100 overflow-hidden">
      <div class="px-6 py-5 border-b border-gray-100 bg-gradient-to-r from-emerald-50 to-white">
        <h2 class="text-xl font-semibold text-gray-800 flex items-center gap-3">
          <span class="inline-block w-3 h-3 rounded-full bg-emerald-500"></span>
          By Company
        </h2>
      </div>

      <div class="flex flex-col lg:flex-row">
        <!-- Table – Left side (takes more space) -->
        <div class="lg:w-3/5 xl:w-7/12 border-r border-gray-100 lg:border-r">
          <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
              <thead class="bg-emerald-600 text-white sticky top-0 z-10">
                <tr>
                  <th scope="col" class="px-6 py-4 text-left font-semibold tracking-wide">Company</th>
                  <th scope="col" class="px-6 py-4 text-right font-semibold tracking-wide">Assets</th>
                  <th scope="col" class="px-6 py-4 text-right font-semibold tracking-wide">Total Cost</th>
                  <th scope="col" class="px-6 py-4 text-left font-semibold tracking-wide">Categories</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-100 bg-white">
                <tr
                  v-for="item in byCompany"
                  :key="item.company"
                  class="hover:bg-emerald-50/60 transition-colors duration-150 cursor-pointer group"
                >
                  <td class="px-6 py-4 font-medium text-gray-900 group-hover:text-emerald-700">
                    {{ item.company }}
                  </td>
                  <td class="px-6 py-4 text-right text-gray-700">{{ item.asset_count }}</td>
                  <td class="px-6 py-4 text-right font-semibold text-gray-800">
                    {{ formatCurrency(item.total_cost) }}
                  </td>
                  <td class="px-6 py-4 text-gray-600">
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-emerald-100 text-emerald-800">
                      {{ item.categories }}
                    </span>
                  </td>
                </tr>

                <!-- Optional: empty state -->
                <tr v-if="!byCompany?.length">
                  <td colspan="4" class="px-6 py-10 text-center text-gray-500 italic">
                    No company data available yet
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Pie Chart – Right side -->
        <div class="lg:w-2/5 xl:w-5/12 p-6 lg:p-8 flex items-center justify-center bg-gradient-to-br from-emerald-50/30 to-white">
          <div class="w-full max-w-[520px] aspect-square">
            <PieChart
              :data="pieData"
              :options="{
                ...pieOptions,
                plugins: {
                  legend: {
                    position: 'bottom',
                    labels: { padding: 20, font: { size: 13 } }
                  },
                  title: {
                    display: true,
                    text: 'Cost Distribution by Company',
                    font: { size: 16, weight: 'bolder' },
                    color: '#1f2937',
                    padding: { bottom: 16 }
                  }
                },
                maintainAspectRatio: true
              }"
            />
          </div>
        </div>
      </div>
    </section>
  </div>
    </div>
  </div>
</template>

