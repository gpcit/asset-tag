<script setup lang="ts">
import { ref, onMounted } from 'vue'
import api from '@/services/api'
import Swal from 'sweetalert2'
import NavBar from '@/components/NavBar.vue'
import * as XLSX from 'xlsx'
import { saveAs } from 'file-saver'

interface Asset {
  id: number
  person_in_charge: string
  department: string
  invoice_number?: string
  invoice_date?: string
  cost?: number
  supplier?: string
  model_number?: string
  specs?: string
  remarks?: string
  date_deployed?: string
  category?: { name: string }
  company?: { name: string }
  // This matches your Laravel 'with' structure
  asset_code?: { unique_code: string } 
  assetCode?: { unique_code: string }
}

const searchCode = ref('')
const foundAsset = ref<Asset | null>(null)
const suggestions = ref<string[]>([])
const loading = ref(false)
const allAssets = ref<Asset[]>([])

/* ================= SEARCH BY UNIQUE CODE ================= */
const searchUniqueCode = async (code?: string) => {
  const query = code || searchCode.value.trim()
  if (!query) return

  loading.value = true
  foundAsset.value = null
  suggestions.value = []

  try {
    // Matches your route: Route::get('/assets/by-unique-code', ...)
    const res = await api.get('/assets/by-unique-code', {
      params: { unique_code: query }
    })

    // In your PHP controller, you return: ['unique_code' => ..., 'asset' => ...]
    // We store it so the UI displays correctly
    foundAsset.value = {
      ...res.data.asset,
      asset_code: { unique_code: res.data.unique_code }
    }

    searchCode.value = query
  } catch (err: any) {
    Swal.fire({
      icon: 'error',
      title: 'Not Found',
      text: 'No asset found with this unique code.'
    })
  } finally {
    loading.value = false
  }
}

/* ================= FETCH SUGGESTIONS ================= */
const fetchSuggestions = async () => {
  const query = searchCode.value.trim()
  if (!query) {
    suggestions.value = []
    return
  }

  try {
    const res = await api.get('/assets/unique-code-suggestions', { params: { q: query } })
    suggestions.value = res.data
  } catch (err) {
    console.error(err)
  }
}

const selectSuggestion = async (code: string) => {
  searchCode.value = code
  suggestions.value = []
  await searchUniqueCode(code)
}

/* ================= FETCH ALL ASSETS FOR EXPORT ================= */
const fetchAllAssetsWithUniqueCode = async () => {
  try {
    // Matches your controller: $query->whereHas('assetCode')
    const res = await api.get('/assets', { params: { has_unique_code: true } })
    allAssets.value = res.data
  } catch (err) {
    console.error('Fetch Error:', err)
  }
}

/* ================= EXPORT TO EXCEL ================= */
const exportToExcel = () => {
  if (!allAssets.value.length) {
    Swal.fire({ icon: 'info', title: 'No data', text: 'No assets with unique codes found.' })
    return
  }

  const worksheetData = allAssets.value.map(a => {
    // Robust check for the unique code in the nested relationship
    // Laravel usually returns 'asset_code' or 'assetCode'
    const codeValue = a.asset_code?.unique_code || 
                      a.assetCode?.unique_code || 
                      (a as any).unique_code || 
                      '-';

    return {
      'Unique Code': codeValue,
      'Company': a.company?.name || '-',
      'Person In-charge': a.person_in_charge || '-',
      'Department': a.department || '-',
      'Category': a.category?.name || '-',
      'Model Number': a.model_number || '-',
      'Supplier': a.supplier || '-',
      'Cost': a.cost ?? '-',
      'Invoice Number': a.invoice_number || '-',
      'Invoice Date': a.invoice_date || '-',
      'Date Deployed': a.date_deployed || '-',
      'Specifications': a.specs || '-',
      'Remarks': a.remarks || '-',
    }
  })

  const worksheet = XLSX.utils.json_to_sheet(worksheetData)
  const workbook = XLSX.utils.book_new()
  XLSX.utils.book_append_sheet(workbook, worksheet, 'Assets')

  const excelBuffer = XLSX.write(workbook, { bookType: 'xlsx', type: 'array' })
  const blob = new Blob([excelBuffer], { type: 'application/octet-stream' })
  saveAs(blob, 'Assets_Inventory.xlsx')
}

onMounted(() => {
  fetchAllAssetsWithUniqueCode()
})
</script>

<template>
  <NavBar />

  <div class="p-6 max-w-4xl mx-auto">
    <div class="flex justify-between items-center mb-6">
      <h2 class="text-2xl font-bold">Asset Management</h2>
      <button
        @click="exportToExcel"
        class="bg-emerald-600 text-white px-4 py-2 rounded hover:bg-emerald-700 shadow flex items-center gap-2"
      >
        📥 Export All to Excel
      </button>
    </div>

    <div class="relative mb-6">
      <div class="flex gap-2">
        <input
          v-model="searchCode"
          @input="fetchSuggestions"
          @keyup.enter="() => searchUniqueCode()"
          type="text"
          placeholder="Enter Unique Code..."
          class="w-full border px-4 py-2 rounded-lg shadow-sm focus:ring focus:ring-emerald-200 outline-none"
        />
        <button
          @click="() => searchUniqueCode()"
          class="bg-emerald-600 text-white px-6 py-2 rounded-lg hover:bg-emerald-700 transition"
        >
          Search
        </button>
      </div>

      <ul v-if="suggestions.length" class="absolute w-full bg-white border rounded shadow-lg mt-1 z-50">
        <li
          v-for="code in suggestions"
          :key="code"
          @click="() => selectSuggestion(code)"
          class="px-4 py-2 hover:bg-emerald-50 cursor-pointer border-b last:border-none"
        >
          {{ code }}
        </li>
      </ul>
    </div>

    <div v-if="foundAsset" class="bg-white border rounded-xl shadow-md p-6">
      <div class="flex justify-between items-start border-b pb-4 mb-4">
        <div>
          <h3 class="text-xl font-bold text-gray-800">
            {{ foundAsset.asset_code?.unique_code || foundAsset.assetCode?.unique_code }}
          </h3>
          <p class="text-emerald-600 font-medium">{{ foundAsset.company?.name || 'No Company' }}</p>
        </div>
        <div class="text-right">
          <span class="text-xs uppercase text-gray-400 font-bold">Status</span>
          <p class="text-sm font-semibold">Active</p>
        </div>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
        <p><span class="text-gray-500">Person In-charge:</span> {{ foundAsset.person_in_charge }}</p>
        <p><span class="text-gray-500">Department:</span> {{ foundAsset.department }}</p>
        <p><span class="text-gray-500">Category:</span> {{ foundAsset.category?.name || '-' }}</p>
        <p><span class="text-gray-500">Supplier:</span> {{ foundAsset.supplier || '-' }}</p>
        <p><span class="text-gray-500">Model:</span> {{ foundAsset.model_number || '-' }}</p>
        <p><span class="text-gray-500">Cost:</span> ₱{{ foundAsset.cost ?? '0' }}</p>
      </div>
    </div>

    <div v-else-if="loading" class="text-center text-gray-400 py-10">
      Searching database...
    </div>
  </div>
</template>