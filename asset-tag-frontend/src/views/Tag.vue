<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
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
  asset_info?: string
  remarks?: string
  date_deployed?: string
  category?: { name: string }
  company?: { name: string; code: string; logo?: string }
  asset_code?: { control_number: string }
}

interface Company {
  id: number
  name: string
  code: string
}

const searchCode = ref('')
const foundAsset = ref<Asset | null>(null)
const suggestions = ref<string[]>([])
const loading = ref(false)
const allAssets = ref<Asset[]>([])
const tagModalRef = ref<InstanceType<typeof AssetFormat> | null>(null)

// ✅ Export modal state
const showExportModal = ref(false)
const companies = ref<Company[]>([])
const selectedCompanyIds = ref<number[]>([])
const exportLoading = ref(false)

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
      invoice_date: res.data.asset.invoice_date,
      department: res.data.asset.department,
      specs: res.data.asset.specs || '',
      asset_code: { control_number: res.data.unique_code },
      category: res.data.asset.category || { name: '' },
      company: res.data.asset.company || { name: '', code: '', logo: '' },
    }

    searchCode.value = query
  } catch (err: any) {
    Swal.fire({
      icon: 'error',
      title: 'Not Found',
      text: 'No asset found with this control number.'
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

    allAssets.value = res.data.map((asset: any) => ({
      ...asset,
      invoice_date: asset.invoice_date || '',
      specs: asset.specs || '',
      asset_code: asset.asset_code
        ? { control_number: asset.asset_code.control_number || '' }
        : { control_number: '' },
      category: asset.category || { name: '' },
      company: asset.company || { name: '', code: '', logo: '' },
    }))

    // ✅ Extract unique companies from assets
    const companyMap = new Map<number, Company>()
    res.data.forEach((asset: any) => {
      if (asset.company && asset.company.id) {
        companyMap.set(asset.company.id, {
          id: asset.company.id,
          name: asset.company.name,
          code: asset.company.code,
        })
      }
    })
    companies.value = Array.from(companyMap.values()).sort((a, b) => a.name.localeCompare(b.name))

  } catch (err) {
    console.error('Fetch Error:', err)
  }
}

// ✅ Open export modal
const openExportModal = () => {
  selectedCompanyIds.value = companies.value.map(c => c.id) // select all by default
  showExportModal.value = true
}

// ✅ Toggle company selection
const toggleCompany = (id: number) => {
  if (selectedCompanyIds.value.includes(id)) {
    selectedCompanyIds.value = selectedCompanyIds.value.filter(c => c !== id)
  } else {
    selectedCompanyIds.value.push(id)
  }
}

const selectAll = () => {
  selectedCompanyIds.value = companies.value.map(c => c.id)
}

const deselectAll = () => {
  selectedCompanyIds.value = []
}

// ✅ Filtered assets based on selected companies
const filteredAssetsForExport = computed(() => {
  if (selectedCompanyIds.value.length === 0) return []
  return allAssets.value.filter(a => {
    const companyId = (a.company as any)?.id
    return selectedCompanyIds.value.includes(companyId)
  })
})

const exportToExcel = async () => {
  if (filteredAssetsForExport.value.length === 0) {
    Swal.fire({ icon: 'info', title: 'No data', text: 'No assets found for selected companies.' })
    return
  }

  exportLoading.value = true

  const formatValue = (val: any) => {
    if (val === null || val === undefined) return '-'
    const strVal = String(val)
    return strVal.split(',').map((s: string) => s.trim()).join('\n')
  }

  const worksheetData = filteredAssetsForExport.value.map(a => {
    const codeValue = a.asset_code?.control_number || '-'

    return {
      'Control Number': codeValue,
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
      'Asset Info': formatValue(a.asset_info) || '-',
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
        cell.alignment = { vertical: 'top', wrapText: true }
      })
    }
  })

  worksheet.columns.forEach((column) => {
    if (!column) return
    let maxLength = 0
    column.eachCell?.({ includeEmpty: true }, (cell) => {
      const cellValue = cell.value ? cell.value.toString() : ''
      const cellLength = cellValue.split('\n').reduce((max: number, line: string) =>
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

  // ✅ File name reflects selected companies
  const selectedNames = companies.value
    .filter(c => selectedCompanyIds.value.includes(c.id))
    .map(c => c.code)
    .join('_')

  saveAs(blob, `Asset_Tagging_${selectedNames}.xlsx`)

  exportLoading.value = false
  showExportModal.value = false
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
        @click="openExportModal"
        class="bg-emerald-600 text-white px-4 py-2 rounded hover:bg-emerald-700 shadow flex items-center gap-2">
        📥 Export to Excel
      </button>
    </div>

    <!-- ✅ Export Modal -->
    <div v-if="showExportModal" class="modal-overlay" @click.self="showExportModal = false">
      <div class="export-modal">
        <div class="export-header">
          <h3 class="export-title">Select Companies to Export</h3>
          <button @click="showExportModal = false" class="close-btn">✕</button>
        </div>

        <div class="export-actions">
          <button @click="selectAll" class="action-btn">Select All</button>
          <button @click="deselectAll" class="action-btn-outline">Deselect All</button>
          <span class="selected-count">{{ selectedCompanyIds.length }} selected</span>
        </div>

        <div class="company-list">
          <label
            v-for="company in companies"
            :key="company.id"
            class="company-item"
          >
            <input
              type="checkbox"
              :value="company.id"
              :checked="selectedCompanyIds.includes(company.id)"
              @change="toggleCompany(company.id)"
              class="company-checkbox"
            />
            <span class="company-label">
              <span class="company-code-badge">{{ company.code }}</span>
              {{ company.name }}
            </span>
          </label>
        </div>

        <div class="export-footer">
          <span class="asset-count">
            {{ filteredAssetsForExport.length }} asset(s) will be exported
          </span>
          <div class="footer-buttons">
            <button
              @click="showExportModal = false"
              class="cancel-btn"
            >
              Cancel
            </button>
            <button
              @click="exportToExcel"
              :disabled="selectedCompanyIds.length === 0 || exportLoading"
              class="confirm-btn"
            >
              {{ exportLoading ? 'Exporting...' : '📥 Export' }}
            </button>
          </div>
        </div>
      </div>
    </div>

    <div class="relative mb-6">
      <div class="flex gap-2">
        <input
          v-model="searchCode"
          @input="fetchSuggestions"
          @keyup.enter="() => searchUniqueCode()"
          type="text"
          placeholder="Enter Control Number..."
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
            {{ foundAsset.asset_code?.control_number }}
          </h3>
          <p class="text-emerald-600 font-medium">{{ foundAsset.company?.name || 'No Company' }}</p>
        </div>
        <div class="text-right">
          <span class="text-xs uppercase text-gray-400 font-bold">Status</span>
          <p class="text-sm font-semibold">Active</p>
        </div>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm mb-4">
        <p><span class="text-gray-500">Department:</span> {{ foundAsset.department }}</p>
        <p><span class="text-gray-500">Invoice Date:</span> {{ foundAsset.invoice_date }}</p>
        <p><span class="text-gray-500">Specification:</span> {{ foundAsset.specs }}</p>
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

<style scoped>
.modal-overlay {
  position: fixed;
  top: 0;
  left: 0;
  width: 100vw;
  height: 100vh;
  background: rgba(0, 0, 0, 0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
}

.export-modal {
  background: white;
  border-radius: 12px;
  width: 90%;
  max-width: 520px;
  box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
  overflow: hidden;
}

.export-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 20px 24px;
  border-bottom: 1px solid #e5e7eb;
  background: linear-gradient(135deg, #e8f5f0, #d4ebe3);
}

.export-title {
  font-size: 18px;
  font-weight: 700;
  color: #1a5c4a;
}

.close-btn {
  background: #4a9b7f;
  color: white;
  border: none;
  border-radius: 50%;
  width: 30px;
  height: 30px;
  cursor: pointer;
  font-size: 16px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.export-actions {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 12px 24px;
  border-bottom: 1px solid #f3f4f6;
}

.action-btn {
  background: #2d6b54;
  color: white;
  border: none;
  border-radius: 6px;
  padding: 6px 14px;
  font-size: 13px;
  cursor: pointer;
}

.action-btn:hover {
  background: #235241;
}

.action-btn-outline {
  background: white;
  color: #2d6b54;
  border: 1px solid #2d6b54;
  border-radius: 6px;
  padding: 6px 14px;
  font-size: 13px;
  cursor: pointer;
}

.action-btn-outline:hover {
  background: #f0faf5;
}

.selected-count {
  margin-left: auto;
  font-size: 13px;
  color: #6b7280;
}

.company-list {
  padding: 12px 24px;
  max-height: 300px;
  overflow-y: auto;
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.company-item {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px 12px;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  cursor: pointer;
  transition: background 0.15s;
}

.company-item:hover {
  background: #f0faf5;
}

.company-checkbox {
  width: 16px;
  height: 16px;
  accent-color: #2d6b54;
  cursor: pointer;
}

.company-label {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 14px;
  color: #374151;
}

.company-code-badge {
  background: #2d6b54;
  color: white;
  font-size: 11px;
  font-weight: 700;
  padding: 2px 8px;
  border-radius: 20px;
}

.export-footer {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 16px 24px;
  border-top: 1px solid #e5e7eb;
  background: #f9fafb;
}

.asset-count {
  font-size: 13px;
  color: #6b7280;
}

.footer-buttons {
  display: flex;
  gap: 10px;
}

.cancel-btn {
  background: white;
  color: #374151;
  border: 1px solid #d1d5db;
  border-radius: 8px;
  padding: 8px 18px;
  font-size: 14px;
  cursor: pointer;
}

.cancel-btn:hover {
  background: #f3f4f6;
}

.confirm-btn {
  background: #2d6b54;
  color: white;
  border: none;
  border-radius: 8px;
  padding: 8px 18px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
}

.confirm-btn:hover:not(:disabled) {
  background: #235241;
}

.confirm-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}
</style>