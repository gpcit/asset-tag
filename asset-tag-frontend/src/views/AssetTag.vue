<script setup lang="ts">
import NavBar from '@/components/NavBar.vue'
import { ref, computed, watch} from 'vue'
import Swal from 'sweetalert2'
import { useUserStore } from '@/stores/user'
import api from '@/services/api'
import { useRouter } from 'vue-router'
import { saveAs } from 'file-saver'
import ExcelJS from 'exceljs'
import AssetFormat from '@/components/AssetFormat.vue'

/* ------------------
 Router
------------------ */
const router = useRouter()

/* ------------------
 User
------------------ */
interface User {
  id?: number
  name?: string
  username?: string
  role?: 'admin' | 'staff'
}

interface Employee {
  id: number
  name: string
  department_id?: number
}

const user = ref<User>(JSON.parse(localStorage.getItem('user') || '{}'))

watch(
  () => localStorage.getItem('user'),
  (val) => {
    if (val) user.value = JSON.parse(val)
  }
)

/* ------------------
 Types
------------------ */
interface Category {
  id: number
  name: string
}

interface Company {
  id: number
  name: string
  logo?: string | null
  code?: string
}

interface Department {
  id: number
  name: string
}

interface AssetForm {
  person_in_charge_id?: number
  department_id?: number

  invoiceNumber?: string
  invoiceDate?: string
  cost?: number
  supplier?: string
  modelNumber?: string
  specs?: string
  asset_info?: string
  remarks?: string

  dateDeployed?: string
  dateReturned?: string

  categoryId?: number
  companyId?: number
  is_active?: boolean
}

interface Asset {
  id: number
  invoice_number?: string
  invoice_date?: string
  cost?: number
  supplier?: string
  model_number?: string
  specs?: string
  asset_info?: string
  remarks?: string
  date_deployed?: string
  date_returned?: string
  category_id?: number
  company_id?: number
  department_id?: number
  is_active?: boolean

  company?: Company
  category?: Category
  department?: Department
  employee?: Employee
  uniqueCode?: string
  asset_code?: { control_number: string }
}

/* ------------------
 State
------------------ */
const showCreateModal = ref(false)
const isEditing = ref(false)
const editingAssetId = ref<number | null>(null)

const selectedCategory = ref<number | ''>('')
const selectedCompany = ref<number | ''>('')
const searchQuery = ref('')
const statusFilter = ref<'active' | 'inactive' | 'all'>('active')

const showExportModal = ref(false)
const tagModalRef = ref<InstanceType<typeof AssetFormat> | null>(null)

const showHistoryModal = ref(false)
const selectedAsset = ref<any>(null)

const employees = ref<Employee[]>([])
const loading = ref(true)
const employeeSearch = ref('')
const showEmployeeList = ref(false)
const departmentFilter = ref<number | ''>('')

// Separate state for history modal
const historyEmployeeSearch = ref('')
const showHistoryEmployeeList = ref(false)
const historyForm = ref({
  person_in_charge_id: undefined as number | undefined,
  dateDeployed: '',
  dateReturned: '',
  historyRemarks: ''
})

// Edit history modal state
const showEditHistoryModal = ref(false)
const editingHistory = ref<any>(null)
const historyEditForm = ref({
  employee_id: undefined as number | undefined,
  date_deployed: '',
  date_returned: '',
  historyRemarks: ''
})
const historyEditEmployeeSearch = ref('')
const showHistoryEditEmployeeList = ref(false)

const userStore = useUserStore()

/* ------------------
 Employees
------------------ */
const fetchEmployees = async () => {
  try {
    const res = await api.get('/employees/all')
    employees.value = Array.isArray(res.data) ? res.data : []
    console.log('Employees loaded:', employees.value)
  } catch (err) {
    console.error('Error fetching employees:', err)
    employees.value = []
  }
}

/* ------------------
 Form
------------------ */
const emptyForm = (): AssetForm => ({
  person_in_charge_id: undefined,
  department_id: undefined,

  invoiceNumber: '',
  invoiceDate: '',
  cost: undefined,
  supplier: '',
  modelNumber: '',
  specs: '',
  asset_info: '',
  remarks: '',

  dateDeployed: '',
  dateReturned: '',

  categoryId: undefined,
  companyId: undefined,
  is_active: true,
})

const form = ref<AssetForm>(emptyForm())
const errors = ref<Record<string, string>>({})

/* ------------------
 Computed: filtered employees
------------------ */
const filteredEmployees = computed(() => {
  if (!employeeSearch.value) return employees.value
  return employees.value.filter(emp =>
    emp.name.toLowerCase().includes(employeeSearch.value.toLowerCase())
  )
})

const filteredHistoryEmployees = computed(() => {
  if (!historyEmployeeSearch.value) return employees.value
  return employees.value.filter(emp =>
    emp.name.toLowerCase().includes(historyEmployeeSearch.value.toLowerCase())
  )
})

const filteredHistoryEditEmployees = computed(() => {
  if (!historyEditEmployeeSearch.value) return employees.value
  return employees.value.filter(emp =>
    emp.name.toLowerCase().includes(historyEditEmployeeSearch.value.toLowerCase())
  )
})

const selectEmployee = (emp: Employee) => {
  form.value.person_in_charge_id = emp.id
  employeeSearch.value = emp.name
  showEmployeeList.value = false
}

const selectHistoryEmployee = (emp: Employee) => {
  historyForm.value.person_in_charge_id = emp.id
  historyEmployeeSearch.value = emp.name
  showHistoryEmployeeList.value = false

  historyForm.value.dateReturned = ''

  if (!historyForm.value.dateDeployed) {
    const today = new Date().toISOString().split('T')[0]
    historyForm.value.dateDeployed = today
  }
}

const selectHistoryEditEmployee = (emp: Employee) => {
  historyEditForm.value.employee_id = emp.id
  historyEditEmployeeSearch.value = emp.name
  showHistoryEditEmployeeList.value = false
}

const clearHistoryEmployee = () => {
  historyForm.value.person_in_charge_id = undefined
  historyEmployeeSearch.value = ''
  historyForm.value.dateDeployed = ''

  const today = new Date().toISOString().split('T')[0]
  historyForm.value.dateReturned = today
}

watch(
  () => form.value.person_in_charge_id,
  (newId, oldId) => {
    if (oldId === undefined) {
      if (!newId) {
        employeeSearch.value = ''
        return
      }
      const emp = employees.value.find(e => e.id === Number(newId))
      if (emp) employeeSearch.value = emp.name
      return
    }

    if (!newId) {
      employeeSearch.value = ''
      form.value.dateDeployed = undefined
      return
    }

    const emp = employees.value.find(e => e.id === Number(newId))
    if (!emp) return

    employeeSearch.value = emp.name
    form.value.dateReturned = undefined

    if (!form.value.dateDeployed) {
      form.value.dateDeployed = undefined
    }
  }
)

/* ------------------
 Asset History
------------------ */
const viewHistory = async (asset: any) => {
  try {
    const res = await api.get(`/assets/${asset.id}`)
    selectedAsset.value = res.data

    // ✅ Find the open history row to pre-fill the remarks
    const openHistory = res.data.histories?.find(
      (h: any) => !h.date_returned && h.employee_id === res.data.person_in_charge_id
    )

    historyForm.value = {
      person_in_charge_id: res.data.person_in_charge_id ?? undefined,
      dateDeployed:        res.data.date_deployed || '',
      dateReturned:        res.data.date_returned || '',
      historyRemarks:      openHistory?.remarks || '',  // ✅ pre-fill from open history row
    }

    historyEmployeeSearch.value = res.data.employee?.name || ''
    showHistoryModal.value = true
  } catch (err) {
    console.error('Error fetching asset history:', err)
    Swal.fire('Error', 'Failed to load asset history', 'error')
  }
}

const updateAssetAssignment = async () => {
  try {
    const payload = {
      person_in_charge_id: historyForm.value.person_in_charge_id ?? null,
      date_deployed: historyForm.value.dateDeployed || null,
      date_returned: historyForm.value.dateReturned || null,
      history_remarks: historyForm.value.historyRemarks || '',
    }

    await api.put(`/assets/${selectedAsset.value.id}`, payload)
    Swal.fire('Updated!', 'Asset assignment updated successfully.', 'success')

    showHistoryModal.value = false
    await userStore.fetchAssets()

    if (selectedAsset.value) {
      await viewHistory(selectedAsset.value)
    }
  } catch (err: any) {
    Swal.fire('Error', err.response?.data?.message || 'Update failed', 'error')
  }
}

/* ------------------
 Edit History Entry
------------------ */
const openEditHistoryModal = (history: any) => {
  editingHistory.value = history

  historyEditForm.value = {
    employee_id: history.employee_id ?? undefined,
    date_deployed: history.date_deployed || '',
    date_returned: history.date_returned || '',
    remarks: history.remarks || ''
  }

  if (history.employee) {
    historyEditEmployeeSearch.value = history.employee.name
  } else {
    historyEditEmployeeSearch.value = ''
  }

  showEditHistoryModal.value = true
}

const updateHistoryEntry = async () => {
  try {
    const payload = {
      employee_id: historyEditForm.value.employee_id ?? null,
      date_deployed: historyEditForm.value.date_deployed || null,
      date_returned: historyEditForm.value.date_returned || null,
      remarks: historyEditForm.value.remarks || ''
    }

    await api.put(`/asset-histories/${editingHistory.value.id}`, payload)
    Swal.fire('Updated!', 'History entry updated successfully.', 'success')

    showEditHistoryModal.value = false
    await viewHistory(selectedAsset.value)
  } catch (err: any) {
    Swal.fire('Error', err.response?.data?.message || 'Update failed', 'error')
  }
}

/* ------------------
 Delete History Entry
------------------ */
const deleteHistoryEntry = async (historyId: number) => {
  const res = await Swal.fire({
    title: 'Delete this history entry?',
    text: 'This action cannot be undone.',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Yes, delete it',
    confirmButtonColor: '#dc2626',
    cancelButtonText: 'Cancel'
  })

  if (!res.isConfirmed) return

  try {
    await api.delete(`/asset-histories/${historyId}`)
    Swal.fire('Deleted!', 'History entry has been removed.', 'success')
    await viewHistory(selectedAsset.value)
  } catch (err: any) {
    Swal.fire('Error', err.response?.data?.message || 'Failed to delete history entry', 'error')
  }
}

/* ------------------
 Pagination & Filters
------------------ */
const currentPage = ref(1)
const itemsPerPage = ref(10)

const filteredAssets = computed<Asset[]>(() => {
  const query = searchQuery.value.trim().toLowerCase()

  return userStore.assets
    .filter((asset: Asset) => {
      if (statusFilter.value === 'active' && !asset.is_active) return false
      if (statusFilter.value === 'inactive' && asset.is_active) return false
      if (selectedCategory.value !== '' && asset.category_id !== selectedCategory.value) return false
      if (selectedCompany.value !== '' && asset.company_id !== selectedCompany.value) return false
      if (departmentFilter.value !== '' && asset.department_id !== departmentFilter.value) return false

      if (query) {
        return (
          (asset.company?.name ?? '').toLowerCase().includes(query) ||
          (asset.asset_info ?? '').toLowerCase().includes(query) ||
          (asset.employee?.name ?? '').toLowerCase().includes(query) ||
          ((asset as any).person_in_charge ?? '').toLowerCase().includes(query) ||
          (asset.category?.name ?? '').toLowerCase().includes(query) ||
          (asset.specs ?? '').toLowerCase().includes(query) ||
          (asset.asset_code?.control_number ?? '').toLowerCase().includes(query)
        )
      }

      return true
    })
    .sort((a, b) => b.id - a.id)
})

const paginatedAssets = computed(() => {
  const start = (currentPage.value - 1) * itemsPerPage.value
  return filteredAssets.value.slice(start, start + itemsPerPage.value)
})

const totalPages = computed(() =>
  Math.ceil(filteredAssets.value.length / itemsPerPage.value)
)

watch([selectedCategory, selectedCompany, searchQuery, statusFilter, departmentFilter], async () => {
  currentPage.value = 1
  await userStore.fetchAssets()
})

/* ------------------
 Payload Mapper
------------------ */
const mapFormToPayload = (f: AssetForm) => ({
  person_in_charge_id: f.person_in_charge_id ?? null,
  department_id: f.department_id ?? null,
  invoice_number: f.invoiceNumber || '',
  invoice_date: f.invoiceDate || null,
  cost: f.cost !== undefined ? Number(f.cost) : null,
  supplier: f.supplier || '',
  model_number: f.modelNumber || '',
  specs: f.specs || '',
  asset_info: f.asset_info || '',
  remarks: f.remarks || '',
  
  // Ensure these match your AssetForm interface and backend columns
  date_deployed: f.dateDeployed || null, 
  date_returned: f.dateReturned || null,

  category_id: f.categoryId ?? null,
  company_id: f.companyId ?? null,
  is_active: f.is_active ?? true,
})

/* ------------------
 Validation
------------------ */
const validateForm = () => {
  errors.value = {}

  if (!form.value.invoiceNumber?.trim()) errors.value.invoiceNumber = 'Invoice Number is required'
  if (!form.value.supplier?.trim()) errors.value.supplier = 'Supplier is required'
  if (!form.value.modelNumber?.trim()) errors.value.modelNumber = 'Model Number is required'
  if (!form.value.specs?.trim()) errors.value.specs = 'Specification is required'
  if (!form.value.companyId) errors.value.companyId = 'Company is required'
  if (!form.value.categoryId) errors.value.categoryId = 'Category is required'

  return Object.keys(errors.value).length === 0
}

/* ------------------
 Form actions
------------------ */
const resetFilters = () => {
  selectedCategory.value = ''
  selectedCompany.value = ''
  searchQuery.value = ''
  departmentFilter.value = ''
}

const openCreateModal = () => {
  isEditing.value = false
  editingAssetId.value = null
  form.value = emptyForm()
  errors.value = {}
  employeeSearch.value = ''
  showCreateModal.value = true
}

const openEditModal = (asset: Asset) => {
  isEditing.value = true
  editingAssetId.value = asset.id

  form.value = {
    // ADD THESE TWO LINES:
    person_in_charge_id: asset.person_in_charge_id || asset.employee?.id || undefined,
    dateDeployed: asset.date_deployed || '',
    
    // Existing fields:
    invoiceNumber: asset.invoice_number || '',
    invoiceDate: asset.invoice_date || '',
    cost: asset.cost || undefined,
    supplier: asset.supplier || '',
    modelNumber: asset.model_number || '',
    specs: asset.specs || '',
    asset_info: asset.asset_info || '',
    remarks: asset.remarks || '',
    department_id: asset.department_id ?? undefined,
    categoryId: asset.category_id ?? undefined,
    companyId: asset.company_id ?? undefined,
    is_active: asset.is_active ?? true,
  }

  // Also update the search text so the UI shows the name
  if (asset.employee) {
    employeeSearch.value = asset.employee.name
  } else {
    employeeSearch.value = ''
  }

  showCreateModal.value = true
}

/* ------------------
 Submit
------------------ */
const submitForm = async () => {
  console.log('Submitting payload:', mapFormToPayload(form.value))
  if (!validateForm()) {
    Swal.fire('Validation Error', 'Please fix the highlighted fields.', 'error')
    return
  }

  try {
    const payload = mapFormToPayload(form.value)

    if (isEditing.value && editingAssetId.value) {
      // ── EDIT ──────────────────────────────────────────────
      await api.put(`/assets/${editingAssetId.value}`, payload)
      Swal.fire('Updated!', 'Asset updated successfully.', 'success')
      showCreateModal.value = false
      form.value = emptyForm()
      await userStore.fetchAssets()

    } else {
      // ── CREATE ────────────────────────────────────────────
      const res = await api.post('/assets', payload)
      const newAsset = res.data

      // Backend returns control number inside assetCode or asset_code
      const controlNumber =
        newAsset.assetCode?.control_number ||
        newAsset.asset_code?.control_number

      showCreateModal.value = false
      form.value = emptyForm()
      await userStore.fetchAssets()

      //  Auto-download tag immediately — no modal needed
      if (controlNumber) {
        await tagModalRef.value?.autoDownloadTag(newAsset, controlNumber)
      }

      await Swal.fire({
        icon: 'success',
        title: 'Asset Created!',
        html: 'Asset has been successfully created.<br>The tag has been downloaded automatically.',
        confirmButtonText: 'Close',
        confirmButtonColor: '#059669',
      })
    }
  } catch (err: any) {
    Swal.fire('Error', err.response?.data?.message || 'Operation failed', 'error')
  }
}

watch(form, () => (errors.value = {}), { deep: true })

/* ------------------
 Delete
------------------ */
const deleteAsset = async (asset: Asset) => {
  const res = await Swal.fire({
    title: 'Delete asset?',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Yes',
  })

  if (!res.isConfirmed) return

  await api.delete(`/assets/${asset.id}`)
  await userStore.fetchAssets()
  Swal.fire('Deleted', 'Asset removed.', 'success')
}

/* ------------------
 Excel Export
------------------ */
const allFields = [
  { key: 'person_in_charge', label: 'Person In-Charge' },
  { key: 'department', label: 'Department' },
  { key: 'company_id', label: 'Company' },
  { key: 'category_id', label: 'Category' },
  { key: 'invoice_number', label: 'Invoice Number' },
  { key: 'invoice_date', label: 'Invoice Date' },
  { key: 'cost', label: 'Cost' },
  { key: 'model_number', label: 'Model Number' },
  { key: 'supplier', label: 'Supplier' },
  { key: 'asset_info', label: 'Asset Info' },
  { key: 'specs', label: 'Specifications' },
  { key: 'date_deployed', label: 'Date Deployed' },
  { key: 'remarks', label: 'Remarks' },
]

const exportFields = ref<string[]>([])
const selectAll = ref(false)

const toggleSelectAll = () => {
  exportFields.value = selectAll.value ? allFields.map(f => f.key) : []
}

watch(exportFields, (newVal) => {
  selectAll.value = newVal.length === allFields.length
}, { deep: true })

const formatCellValue = (value: any): string => {
  if (value === null || value === undefined) return ''
  if (typeof value === 'boolean') return value ? 'Yes' : 'No'
  if (typeof value === 'object') return JSON.stringify(value)
  return String(value)
}

const exportExcel = async () => {
  if (!exportFields.value.length) {
    Swal.fire({
      icon: 'warning',
      title: 'No Fields Selected',
      text: 'Please select at least one field.',
    })
    return
  }

  const workbook = new ExcelJS.Workbook()
  const worksheet = workbook.addWorksheet('Assets')

  worksheet.mergeCells('A1', `${String.fromCharCode(65 + exportFields.value.length - 1)}1`)
  worksheet.getCell('A1').value = 'Asset Management System'
  worksheet.getCell('A1').font = { size: 40, bold: true }
  worksheet.getCell('A1').alignment = { horizontal: 'center', vertical: 'middle' }
  worksheet.getRow(1).height = 50

  const extractionDate = new Date().toLocaleString('en-US', {
    year: 'numeric', month: 'long', day: 'numeric'
  })
  worksheet.mergeCells('A2', `${String.fromCharCode(65 + exportFields.value.length - 1)}2`)
  worksheet.getCell('A2').value = `Extracted on: ${extractionDate}`
  worksheet.getCell('A2').font = { size: 11, italic: true }
  worksheet.getCell('A2').alignment = { horizontal: 'center', vertical: 'middle' }
  worksheet.getRow(2).height = 20

  worksheet.addRow([])

  const headers = exportFields.value.map(key => {
    const field = allFields.find(f => f.key === key)
    return field ? field.label : key
  })
  worksheet.addRow(headers)

  const headerRow = worksheet.getRow(4)
  headerRow.font = { bold: true }
  headerRow.alignment = { vertical: 'top', wrapText: true }

  filteredAssets.value.forEach(asset => {
    const row: any[] = []
    exportFields.value.forEach(key => {
      let value
      if (key === 'category_id') {
        value = formatCellValue(asset.category?.name)
      } else if (key === 'company_id') {
        value = formatCellValue(asset.company?.name)
      } else if (key === 'department') {
        const dept = userStore.departments?.find((d: Department) => d.id === asset.department_id)
        value = formatCellValue(dept?.name)
      } else {
        value = formatCellValue((asset as any)[key])
      }

      if (typeof value === 'string') value = value.split(',').map((s: string) => s.trim()).join('\n')
      row.push(value)
    })
    worksheet.addRow(row)
  })

  worksheet.eachRow((row, rowNumber) => {
    if (rowNumber >= 4) {
      row.eachCell((cell) => { cell.alignment = { vertical: 'top', wrapText: true } })
    }
  })

  worksheet.columns.forEach((column) => {
    if (!column) return
    let maxLength = 0
    column.eachCell?.({ includeEmpty: true }, (cell) => {
      const cellValue = cell.value ? cell.value.toString() : ''
      const cellLength = cellValue.split('\n').reduce((max: number, line: string) => Math.max(max, line.length), 0)
      maxLength = Math.max(maxLength, cellLength)
    })
    column.width = Math.min(Math.max(maxLength + 2, 10), 50)
  })

  const buffer = await workbook.xlsx.writeBuffer()
  const blob = new Blob([buffer], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' })
  saveAs(blob, 'assets_export.xlsx')
  showExportModal.value = false
}

/* ------------------
 Computed: Combined History
------------------ */
const combinedHistory = computed(() => {
  if (!selectedAsset.value) return []

  const histories = (selectedAsset.value.histories || [])
    .filter((h: any) => {
      // Hide open rows that duplicate the current assignment display
      if (!h.date_returned && h.employee_id === selectedAsset.value.person_in_charge_id) {
        return false
      }
      return true
    })

  const currentAssignment = selectedAsset.value.employee ? {
    id: 'current',
    employee: selectedAsset.value.employee,
    employee_id: selectedAsset.value.person_in_charge_id,
    date_deployed: selectedAsset.value.date_deployed,
    date_returned: selectedAsset.value.date_returned,
    remarks: selectedAsset.value.histories?.find(
      (h: any) => !h.date_returned && h.employee_id === selectedAsset.value.person_in_charge_id
    )?.remarks || '',   // ✅ pull remarks from the open history row
    isCurrent: true
  } : null

  return currentAssignment ? [currentAssignment, ...histories] : histories
})

/* ------------------
 Helper: resolve department name
------------------ */
const getDepartmentName = (departmentId?: number): string => {
  if (!departmentId) return '—'
  const dept = userStore.departments?.find((d: Department) => d.id === departmentId)
  return dept?.name || '—'
}

/* ------------------
 Init
------------------ */
const initData = async () => {
  loading.value = true
  await userStore.initializeData()
  await fetchEmployees()
  loading.value = false
}

const handleTagCreated = async (assetId: number, uniqueCode: string) => {
  await userStore.fetchAssets()
}

initData()
</script>

<template>
  <NavBar />

  <!-- Loading State -->
  <div v-if="loading" class="flex items-center justify-center min-h-screen">
    <div class="text-center">
      <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-emerald-600 mx-auto"></div>
      <p class="mt-4 text-gray-600">Loading assets...</p>
    </div>
  </div>

  <div v-else class="flex gap-6 p-4 pt-20 items-start">
    <!-- Sidebar / Filters -->
    <div class="w-80 p-6 bg-white shadow-xl rounded-xl flex-shrink-0">
      <h3 class="text-lg font-semibold mb-2">Filter Assets</h3>

      <!-- Search Bar -->
      <div class="mb-4">
        <input v-model="searchQuery" type="text" placeholder="Search by Employee, Company, Category, Specs or Asset Info" class="w-full border rounded px-3 py-2 text-sm"/>
      </div>

      <!-- Category -->
      <div class="mb-4">
        <label class="block text-sm font-medium mb-1">Category</label>
        <select v-model="selectedCategory" class="w-full border rounded px-3 py-2">
          <option value="">All Categories</option>
          <option v-for="cat in userStore.categories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
        </select>
      </div>

      <!-- Status -->
      <div class="mb-4">
        <label class="block text-sm font-medium mb-1">Status</label>
        <select v-model="statusFilter" class="w-full border rounded px-3 py-2 text-sm">
          <option value="">All</option>
          <option value="active">Active</option>
          <option value="inactive">Inactive</option>
        </select>
      </div>

      <!-- Department Filter -->
      <div class="mb-4">
        <label class="block text-sm font-medium mb-1">Department</label>
        <select v-model="departmentFilter" class="w-full border rounded px-3 py-2 text-sm">
          <option value="">All Departments</option>
          <option v-for="dept in userStore.departments" :key="dept.id" :value="dept.id">
            {{ dept.name }}
          </option>
        </select>
      </div>

      <!-- Company -->
      <div class="mb-4">
        <label class="block text-sm font-medium mb-1">Company</label>
        <select v-model="selectedCompany" class="w-full border rounded px-3 py-2">
          <option value="">All Companies</option>
          <option v-for="comp in userStore.companies" :key="comp.id" :value="comp.id">{{ comp.name }}</option>
        </select>
      </div>

      <div class="flex flex-col gap-2">
        <button @click="openCreateModal" class="w-full bg-emerald-600 text-white py-2 rounded">Create New Asset</button>
        <button v-if="user.role === 'admin'" @click="showExportModal = true" class="px-3 py-1 bg-emerald-600 text-white rounded"> 📥 Export to Excel</button>
        <button v-if="selectedCategory || selectedCompany || departmentFilter" @click="resetFilters" class="w-full bg-gray-200 py-2 rounded">Clear Filters</button>
      </div>
    </div>

    <!-- Main Table -->
    <div class="flex-1 overflow-x-auto border border-gray-200 rounded shadow-sm">
      <table class="min-w-full divide-y divide-gray-200 text-sm">
        <thead class="bg-emerald-900 text-white">
          <tr>
            <th class="px-3 py-1 font-semibold w-24">Control Number</th>
            <th class="px-3 py-1 font-semibold w-24">Company</th>
            <th class="px-3 py-1 font-semibold w-24">Category</th>
            <th class="px-3 py-1 font-semibold w-20">Model #</th>
            <th class="px-3 py-1 font-semibold w-24">Supplier</th>
            <th class="px-3 py-1 font-semibold w-32">Specification</th>
            <th class="px-3 py-1 font-semibold w-32">Asset Info</th>
            <th class="px-3 py-1 font-semibold w-20 text-center">Actions</th>
          </tr>
        </thead>
        <tbody class="bg-white divide-y divide-gray-200">
          <tr v-for="asset in paginatedAssets" :key="asset.id" class="hover:bg-emerald-50">
            <td class="px-3 py-1 break-words uppercase">{{ asset.asset_code?.control_number || '-' }}</td>
            <td class="px-3 py-1 break-words uppercase">{{ asset.company?.name || '-' }}</td>
            <td class="px-3 py-1 break-words uppercase">{{ asset.category?.name || '-' }}</td>
            <td class="px-3 py-1 break-words uppercase">{{ asset.model_number || '-' }}</td>
            <td class="px-3 py-1 break-words uppercase">{{ asset.supplier || '-' }}</td>
            <td class="px-3 py-1 break-words uppercase">{{ asset.specs || '-' }}</td>
            <td class="px-3 py-1 break-words uppercase">{{ asset.asset_info }}</td>
            <td class="px-3 py-1 text-center whitespace-nowrap justify-center gap-1">
              <button @click="openEditModal(asset)" class="bg-blue-900 hover:bg-blue-600 text-white px-2 py-1 rounded text-sm font-medium me-3" title="Edit">✏️</button>
              <button v-if="user.role === 'admin'" @click="deleteAsset(asset)" class="bg-red-900 hover:bg-red-700 text-white px-2 py-1 rounded text-sm font-medium me-3" title="Delete">🗑️</button>
              <button v-if="user.role === 'admin'" @click="viewHistory(asset)" class="bg-green-600 hover:bg-green-900 text-white px-2 py-1 rounded text-sm font-medium me-3" title="View">🔍</button>
            </td>
          </tr>
        </tbody>
      </table>
      <p v-if="filteredAssets.length === 0" class="text-center text-gray-500 mt-2 py-2">No assets found.</p>

      <!-- Pagination Controls -->
      <div class="flex justify-between items-center p-2 mt-2 bg-white">
        <button
          @click="currentPage = Math.max(1, currentPage - 1)"
          :disabled="currentPage === 1"
          class="px-3 py-1 bg-gray-200 rounded disabled:opacity-50"
        >
          Previous
        </button>
        <span class="text-sm">Page {{ currentPage }} of {{ totalPages }}</span>
        <button
          @click="currentPage = Math.min(totalPages, currentPage + 1)"
          :disabled="currentPage === totalPages"
          class="px-3 py-1 bg-gray-200 rounded disabled:opacity-50"
        >
          Next
        </button>
      </div>
    </div>
  </div>

  <!-- Create/Edit Modal -->
  <div v-if="showCreateModal" class="fixed inset-0 flex items-center justify-center bg-black/50 z-50">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-2xl p-4 max-h-[90vh] overflow-y-auto">
      <h2 class="text-lg font-bold mb-3">{{ isEditing ? 'Edit Asset' : 'Create New Asset' }}</h2>

      <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
        <!-- Company -->
        <div>
          <label class="block text-sm font-medium mb-1">Company <span class="text-red-500">*</span></label>
          <select v-model="form.companyId" class="w-full border px-2 py-1 rounded text-sm" :class="errors.companyId ? 'border-red-500' : 'border-gray-300'">
            <option value="">Select Company</option>
            <option v-for="comp in userStore.companies" :key="comp.id" :value="comp.id">{{ comp.name }}</option>
          </select>
          <p v-if="errors.companyId" class="text-xs text-red-500 mt-1">{{ errors.companyId }}</p>
        </div>

        <!-- Category -->
        <div>
          <label class="block text-sm font-medium mb-1">Category <span class="text-red-500">*</span></label>
          <select v-model="form.categoryId" class="w-full border px-2 py-1 rounded text-sm" :class="errors.categoryId ? 'border-red-500' : 'border-gray-300'">
            <option value="">Select Category</option>
            <option v-for="cat in userStore.categories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
          </select>
          <p v-if="errors.categoryId" class="text-xs text-red-500 mt-1">{{ errors.categoryId }}</p>
        </div>

        <!-- Department -->
        <div>
          <label class="block text-sm font-medium mb-1">Department</label>
          <select v-model="form.department_id" class="w-full border px-2 py-1 rounded text-sm border-gray-300">
            <option :value="undefined">Select Department</option>
            <option v-for="dept in userStore.departments" :key="dept.id" :value="dept.id">
              {{ dept.name }}
            </option>
          </select>
        </div>

        <!-- Invoice Number -->
        <div>
          <label class="block text-sm font-medium mb-1">Invoice Number <span class="text-red-500">*</span></label>
          <input v-model="form.invoiceNumber" type="text" class="w-full border px-2 py-1 rounded text-sm" :class="errors.invoiceNumber ? 'border-red-500' : 'border-gray-300'" />
          <p v-if="errors.invoiceNumber" class="text-xs text-red-500 mt-1">{{ errors.invoiceNumber }}</p>
        </div>

        <!-- Invoice Date -->
        <div>
          <label class="block text-sm font-medium mb-1">Invoice Date</label>
          <input v-model="form.invoiceDate" type="date" class="w-full border px-2 py-1 rounded text-sm"/>
        </div>

        <!-- Cost -->
        <div>
          <label class="block text-sm font-medium mb-1">Cost</label>
          <input v-model.number="form.cost" type="number" step="0.01" class="w-full border px-2 py-1 rounded text-sm" />
        </div>

        <!-- Supplier -->
        <div>
          <label class="block text-sm font-medium mb-1">Supplier <span class="text-red-500">*</span></label>
          <input v-model="form.supplier" type="text" class="w-full border px-2 py-1 rounded text-sm" :class="errors.supplier ? 'border-red-500' : 'border-gray-300'" />
          <p v-if="errors.supplier" class="text-xs text-red-500 mt-1">{{ errors.supplier }}</p>
        </div>

        <!-- Model Number -->
        <div>
          <label class="block text-sm font-medium mb-1">Model Number <span class="text-red-500">*</span></label>
          <input v-model="form.modelNumber" type="text" class="w-full border px-2 py-1 rounded text-sm" :class="errors.modelNumber ? 'border-red-500' : 'border-gray-300'" />
          <p v-if="errors.modelNumber" class="text-xs text-red-500 mt-1">{{ errors.modelNumber }}</p>
        </div>

        <!-- Specification -->
        <div class="col-span-1 md:col-span-2">
          <label class="block text-sm font-medium mb-1">Specification <span class="text-red-500">*</span></label>
          <textarea v-model="form.specs" rows="3" class="w-full border px-2 py-1 rounded text-sm resize-y" :class="errors.specs ? 'border-red-500' : 'border-gray-300'"></textarea>
          <p v-if="errors.specs" class="text-xs text-red-500 mt-1">{{ errors.specs }}</p>
        </div>

        <!-- Asset Info -->
        <div class="col-span-1 md:col-span-2">
          <label class="block text-sm font-medium mb-1">Asset Info</label>
          <textarea v-model="form.asset_info" rows="3" class="w-full border px-2 py-1 rounded text-sm resize-y"></textarea>
        </div>

        <!-- Remarks -->
        <div class="col-span-1 md:col-span-2">
          <label class="block text-sm font-medium mb-1">Remarks</label>
          <textarea v-model="form.remarks" rows="3" class="w-full border px-2 py-1 rounded text-sm resize-y border-gray-300"></textarea>
        </div>
      </div>

      <div class="flex justify-end gap-2 mt-4">
        <button @click="showCreateModal = false" class="px-3 py-1 bg-gray-300 rounded text-sm">Cancel</button>
        <button
          @click="form.is_active = !form.is_active"
          :class="form.is_active ? 'bg-emerald-600 text-white' : 'bg-red-500 text-white'"
          class="px-4 py-1 rounded text-sm font-semibold transition">
          {{ form.is_active ? 'Active' : 'Inactive' }}
        </button>
        <button @click="submitForm" class="px-3 py-1 bg-emerald-600 text-white rounded text-sm">
          {{ isEditing ? 'Update' : 'Create & Generate Tag' }}
        </button>
      </div>
    </div>
  </div>

  <!-- Asset History Modal -->
  <div v-if="showHistoryModal" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center" @click.self="showHistoryModal = false">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-3xl p-6 max-h-[90vh] overflow-y-auto">

      <div class="flex justify-between items-center mb-4">
        <h2 class="text-lg font-bold">Asset Details & Assignment History</h2>
        <button @click="showHistoryModal = false" class="text-gray-600 hover:text-gray-900 text-2xl">&times;</button>
      </div>

      <div v-if="user.role === 'admin'" class="bg-white-50 p-4 rounded-lg mb-6">
        <h3 class="font-semibold mb-3 text-black-900">Update Assignment</h3>

        <div class="relative mb-4">
          <label class="block text-sm font-medium mb-1">Assigned Employee</label>
          <div class="flex gap-2">
            <div class="flex-1 relative">
              <input v-model="historyEmployeeSearch" type="text" placeholder="Search employee..." class="w-full border px-2 py-1 rounded text-sm border-gray-300"
              @focus="showHistoryEmployeeList = true"/>
              <ul v-if="showHistoryEmployeeList && filteredHistoryEmployees.length" class="absolute z-10 w-full bg-white border rounded shadow max-h-48 overflow-y-auto text-sm mt-1">
                <li v-for="emp in filteredHistoryEmployees" :key="emp.id" class="px-3 py-2 hover:bg-gray-100 cursor-pointer"
                  @click="selectHistoryEmployee(emp)">{{ emp.name }}
                </li>
              </ul>
            </div>
            <button
              v-if="historyForm.person_in_charge_id" @click="clearHistoryEmployee" class="px-3 py-1 bg-red-500 text-white rounded text-sm hover:bg-red-600"
              title="Clear employee (mark as returned)">Clear</button>
          </div>
        </div>

        <div class="grid grid-cols-2 gap-4 mb-4">
          <div>
            <label class="block text-sm font-medium mb-1">Date Deployed</label>
            <input type="date" v-model="historyForm.dateDeployed" class="w-full border px-2 py-1 rounded text-sm"/>
          </div>
          <div>
            <label class="block text-sm font-medium mb-1">Date Returned</label>
            <input type="date" v-model="historyForm.dateReturned" class="w-full border px-2 py-1 rounded text-sm"/>
          </div>
        </div>

        <div class="mb-4">
          <label class="block text-sm font-medium mb-1">Remarks</label>
          <textarea v-model="historyForm.historyRemarks" rows="3" placeholder="Add any notes or remarks about this assignment..." class="w-full border px-2 py-1 rounded text-sm resize-y border-gray-300"></textarea>
        </div>

        <div class="mt-4">
          <button @click="updateAssetAssignment" class="w-full bg-emerald-600 text-white py-2 rounded hover:bg-emerald-700 font-medium">
            💾 Save Assignment Changes
          </button>
        </div>
      </div>

      <hr class="my-4">

      <h3 class="font-semibold mb-2">Asset Information</h3>
      <div class="grid grid-cols-2 gap-4 text-sm mb-6">
        <p><b>Company:</b> {{ selectedAsset?.company?.name || '—' }}</p>
        <p><b>Category:</b> {{ selectedAsset?.category?.name || '—' }}</p>
        <p><b>Department:</b> {{ getDepartmentName(selectedAsset?.department_id) }}</p>
        <p><b>Model:</b> {{ selectedAsset?.model_number || '—' }}</p>
        <p><b>Supplier:</b> {{ selectedAsset?.supplier || '—' }}</p>
        <p><b>Invoice #:</b> {{ selectedAsset?.invoice_number || '—' }}</p>
        <p><b>Specs:</b> {{ selectedAsset?.specs || '—' }}</p>
      </div>

      <hr class="my-4">

      <h3 class="font-semibold mb-2">Assignment History</h3>
      <div class="overflow-x-auto">
        <table class="w-full text-sm border border-gray-300">
          <thead class="bg-gray-100">
            <tr>
              <th class="p-2 border">Employee</th>
              <th class="p-2 border">Deployed</th>
              <th class="p-2 border">Returned</th>
              <th class="p-2 border">Remarks</th>
              <th v-if="user.role === 'admin'" class="p-2 border w-24">Action</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="h in combinedHistory" :key="h.id" class="hover:bg-gray-50" :class="{ 'bg-emerald-50 font-semibold': h.isCurrent }">
              <td class="p-2 border">
                {{ h.employee?.name || '—' }}
                <span v-if="h.isCurrent" class="ml-2 text-xs bg-emerald-600 text-white px-2 py-0.5 rounded">Current</span>
              </td>
              <td class="p-2 border">{{ h.date_deployed || '—' }}</td>
              <td class="p-2 border">{{ h.date_returned || '—' }}</td>
              <td class="p-2 border">{{ h.remarks || '—' }}</td>
              <td v-if="user.role === 'admin'" class="p-2 border text-center">
                <div v-if="!h.isCurrent" class="flex gap-1 justify-center">
                  <button @click="openEditHistoryModal(h)" class="bg-blue-600 hover:bg-blue-700 text-white px-2 py-1 rounded text-xs font-medium" title="Edit">✏️</button>
                  <button @click="deleteHistoryEntry(h.id)" class="bg-red-600 hover:bg-red-700 text-white px-2 py-1 rounded text-xs font-medium" title="Delete">🗑️</button>
                </div>
                <span v-else class="text-gray-400 text-xs">—</span>
              </td>
            </tr>
            <tr v-if="combinedHistory.length === 0">
              <td :colspan="user.role === 'admin' ? 5 : 4" class="p-2 text-center text-gray-500">
                No assignment history
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- Edit History Modal -->
  <div v-if="showEditHistoryModal" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center" @click.self="showEditHistoryModal = false">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-2xl p-6 max-h-[90vh] overflow-y-auto">

      <div class="flex justify-between items-center mb-4">
        <h2 class="text-lg font-bold">Edit History Entry</h2>
        <button @click="showEditHistoryModal = false" class="text-gray-600 hover:text-gray-900 text-2xl">&times;</button>
      </div>

      <div class="relative mb-4">
        <label class="block text-sm font-medium mb-1">Employee</label>
        <input
          v-model="historyEditEmployeeSearch"
          type="text"
          placeholder="Search employee..."
          class="w-full border px-2 py-1 rounded text-sm border-gray-300"
          @focus="showHistoryEditEmployeeList = true"
        />
        <ul v-if="showHistoryEditEmployeeList && filteredHistoryEditEmployees.length" class="absolute z-10 w-full bg-white border rounded shadow max-h-48 overflow-y-auto text-sm mt-1">
          <li
            v-for="emp in filteredHistoryEditEmployees"
            :key="emp.id"
            class="px-3 py-2 hover:bg-gray-100 cursor-pointer"
            @click="selectHistoryEditEmployee(emp)"
          >{{ emp.name }}</li>
        </ul>
      </div>

      <div class="grid grid-cols-2 gap-4 mb-4">
        <div>
          <label class="block text-sm font-medium mb-1">Date Deployed</label>
          <input type="date" v-model="historyEditForm.date_deployed" class="w-full border px-2 py-1 rounded text-sm"/>
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">Date Returned</label>
          <input type="date" v-model="historyEditForm.date_returned" class="w-full border px-2 py-1 rounded text-sm"/>
        </div>
      </div>

      <div class="mb-4">
        <label class="block text-sm font-medium mb-1">Remarks</label>
        <textarea
          v-model="historyEditForm.remarks"
          rows="3"
          placeholder="Add any notes or remarks..."
          class="w-full border px-2 py-1 rounded text-sm resize-y border-gray-300"
        ></textarea>
      </div>

      <div class="flex justify-end gap-2">
        <button @click="showEditHistoryModal = false" class="px-4 py-2 bg-gray-300 rounded text-sm hover:bg-gray-400">Cancel</button>
        <button @click="updateHistoryEntry" class="px-4 py-2 bg-emerald-600 text-white rounded text-sm hover:bg-emerald-700 font-medium">
          💾 Save Changes
        </button>
      </div>
    </div>
  </div>

  <!-- Export to Excel modal -->
  <div v-if="showExportModal" class="fixed inset-0 flex items-center justify-center bg-black/50 z-50">
    <div class="relative bg-white rounded-2xl shadow-xl p-6 w-96 max-w-full z-50">
      <div class="flex justify-between items-center mb-4">
        <h2 class="text-xl font-semibold text-gray-800">Select Fields to Export</h2>
        <button @click="showExportModal = false" class="text-gray-400 hover:text-gray-600 transition-colors">✕</button>
      </div>

      <div class="mb-3 pb-3 border-b border-gray-200">
        <label class="flex items-center cursor-pointer hover:bg-gray-100 p-2 rounded transition-colors">
          <input type="checkbox" v-model="selectAll" @change="toggleSelectAll" class="mr-2 h-5 w-5 text-green-500 border-gray-300 rounded focus:ring-green-400 cursor-pointer"/>
          <span class="text-gray-800 font-semibold select-none">Select All Fields</span>
        </label>
      </div>

      <div class="max-h-64 overflow-y-auto border rounded p-3 mb-4 bg-gray-50">
        <div v-for="field in allFields" :key="field.key" class="flex items-center mb-2 last:mb-0">
          <input type="checkbox" v-model="exportFields" :value="field.key" class="mr-2 h-4 w-4 text-green-500 border-gray-300 rounded focus:ring-green-400 cursor-pointer"/>
          <label class="text-gray-700 text-sm select-none cursor-pointer">{{ field.label }}</label>
        </div>
      </div>

      <div class="flex justify-end space-x-3">
        <button @click="showExportModal = false" class="px-4 py-2 rounded-lg bg-gray-200 text-gray-700 hover:bg-gray-300 transition-colors">Cancel</button>
        <button @click="exportExcel" class="px-4 py-2 rounded-lg bg-emerald-600 text-white hover:bg-emerald-500 transition-colors">Export</button>
      </div>
    </div>
  </div>

  <!-- ✅ AssetFormat mounted here — tagModalRef exposes autoDownloadTag -->
  <AssetFormat ref="tagModalRef" @tagCreated="handleTagCreated" />
</template>