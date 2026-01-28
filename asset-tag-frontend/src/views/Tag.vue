<script setup lang="ts">
import { ref, onMounted } from 'vue'
import api from '@/services/api'
import Swal from 'sweetalert2'
import NavBar from '@/components/NavBar.vue'
import { saveAs } from 'file-saver'
import ExcelJS from 'exceljs'
import AssetFormat from '@/components/AssetFormat.vue'

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
  company?: { name: string; code: string; logo?: string }
  asset_code?: { unique_code: string }
}

const searchCode = ref('')
const foundAsset = ref<Asset | null>(null)
const suggestions = ref<string[]>([])
const loading = ref(false)
const allAssets = ref<Asset[]>([])
const tagModalRef = ref<InstanceType<typeof AssetFormat> | null>(null)

const searchUniqueCode = async (code?: string) => {
  const query = code || searchCode.value.trim()
  if (!query) return

  loading.value = true
  foundAsset.value = null
  suggestions.value = []

  try {
    const res = await api.get('/assets/by-unique-code', {
      params: { unique_code: query }
    })

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

const reprintTag = () => {
  if (foundAsset.value) {
    tagModalRef.value?.openReprintModal(foundAsset.value)
  }
}

const fetchAllAssetsWithUniqueCode = async () => {
  try {
    const res = await api.get('/assets', { params: { has_unique_code: true } })
    allAssets.value = res.data
    console.log('Fetched assets:', allAssets.value.length)
  } catch (err) {
    console.error('Fetch Error:', err)
  }
}

const exportToExcel = async () => {
  if (!allAssets.value.length) {
    Swal.fire({ icon: 'info', title: 'No data', text: 'No assets with unique codes found.' })
    return
  }
  
  const formatValue = (val: any) => {
    if (val === null || val === undefined) return '-'
    const strVal = String(val)
    return strVal.split(',').map(s => s.trim()).join('\n')
  }
  
  const worksheetData = allAssets.value.map(a => {
    const codeValue = a.asset_code?.unique_code || '-'
    
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
      'Specifications': formatValue(a.specs),
      'Remarks': formatValue(a.remarks),
    }
  })
  
  const headers = Object.keys(worksheetData[0] || {})
  const lastColumn = String.fromCharCode(65 + headers.length - 1)
  
  const workbook = new ExcelJS.Workbook()
  const worksheet = workbook.addWorksheet('Assets')
  
  worksheet.mergeCells('A1', `${lastColumn}1`)
  worksheet.getCell('A1').value = 'Asset Management System (TAGGING)'
  worksheet.getCell('A1').font = { size: 40, bold: true }
  worksheet.getCell('A1').alignment = { horizontal: 'center', vertical: 'middle' }
  worksheet.getRow(1).height = 50
  
  const extractionDate = new Date().toLocaleString('en-US', {
    year: 'numeric',
    month: 'long',
    day: 'numeric'
  })
  worksheet.mergeCells('A2', `${lastColumn}2`)
  worksheet.getCell('A2').value = `Extracted on: ${extractionDate}`
  worksheet.getCell('A2').font = { size: 11, italic: true }
  worksheet.getCell('A2').alignment = { horizontal: 'center', vertical: 'middle' }
  worksheet.getRow(2).height = 20
  
  worksheet.addRow([])
  
  worksheet.addRow(headers)
  
  const headerRow = worksheet.getRow(4)
  headerRow.font = { bold: true }
  headerRow.alignment = { vertical: 'top', wrapText: true }
  
  worksheetData.forEach(row => {
    const rowValues = headers.map(header => (row as any)[header])
    worksheet.addRow(rowValues)
  })
  
  worksheet.eachRow((row, rowNumber) => {
    if (rowNumber >= 4) {
      row.eachCell((cell) => {
        cell.alignment = { 
          vertical: 'top', 
          wrapText: true 
        }
      })
    }
  })
  
  worksheet.columns.forEach((column) => {
    if (!column) return
    
    let maxLength = 0
    column.eachCell?.({ includeEmpty: true }, (cell) => {
      const cellValue = cell.value ? cell.value.toString() : ''
      const cellLength = cellValue.split('\n').reduce((max, line) => 
        Math.max(max, line.length), 0
      )
      maxLength = Math.max(maxLength, cellLength)
    })
    column.width = Math.min(Math.max(maxLength + 2, 10), 50)
  })
  
  const buffer = await workbook.xlsx.writeBuffer()
  const blob = new Blob([buffer], { 
    type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' 
  })
  saveAs(blob, 'Asset_Tagging.xlsx')
}

onMounted(() => {
  fetchAllAssetsWithUniqueCode()
})
</script>

<template>
  <NavBar />

  <div class="p-6 max-w-4xl mx-auto">
    <div class="flex justify-between items-center mb-6">
      <h2 class="text-2xl font-bold">Asset Tagging</h2>
      <button
        @click="exportToExcel"
        class="bg-emerald-600 text-white px-4 py-2 rounded hover:bg-emerald-700 shadow flex items-center gap-2">📥 Export All to Excel
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
            {{ foundAsset.asset_code?.unique_code }}
          </h3>
          <p class="text-emerald-600 font-medium">{{ foundAsset.company?.name || 'No Company' }}</p>
        </div>
        <div class="text-right">
          <span class="text-xs uppercase text-gray-400 font-bold">Status</span>
          <p class="text-sm font-semibold">Active</p>
        </div>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm mb-4">
        <p><span class="text-gray-500">Person In-charge:</span> {{ foundAsset.person_in_charge }}</p>
        <p><span class="text-gray-500">Department:</span> {{ foundAsset.department }}</p>
        <p><span class="text-gray-500">Category:</span> {{ foundAsset.category?.name || '-' }}</p>
        <p><span class="text-gray-500">Supplier:</span> {{ foundAsset.supplier || '-' }}</p>
        <p><span class="text-gray-500">Model:</span> {{ foundAsset.model_number || '-' }}</p>
        <p><span class="text-gray-500">Cost:</span> ₱{{ foundAsset.cost ?? '0' }}</p>
      </div>

      <div class="flex justify-end pt-4 border-t">
        <button
          @click="reprintTag"
          class="bg-yellow-600 hover:bg-yellow-700 text-white px-4 py-2 rounded-lg shadow flex items-center gap-2 transition"
        >
          🏷️ Reprint Tag
        </button>
      </div>
    </div>

    <div v-else-if="loading" class="text-center text-gray-400 py-10">
      Searching database...
    </div>

    <AssetFormat ref="tagModalRef" />
  </div>
</template>