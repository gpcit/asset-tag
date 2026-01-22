<script setup lang="ts">
import NavBar from '@/components/NavBar.vue'
import { ref, computed, watch } from 'vue'
import Swal from 'sweetalert2'
import { useUserStore } from '@/stores/user'
import api from '@/services/api'
import QRCode from 'qrcode'
import html2canvas from 'html2canvas'

/* ===== TYPES ===== */
interface Category { id: number; name: string }
interface Company { id: number; name: string, logo?: string | null, code?: string }

interface AssetForm {
  personInCharge: string
  department: string
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
  date_returned?: string           // backend column
  category_id?: number
  company_id?: number
  is_active?: boolean             // made optional to avoid TS errors
  company?: Company
  category?: Category
  uniqueCode?: string
}

interface TaggingAsset extends Asset {
  uniqueCode: string
}

/* ===== STATE ===== */
const showCreateModal = ref(false)
const isEditing = ref(false)
const editingAssetId = ref<number | null>(null)

const selectedCategory = ref<number | ''>('')
const selectedCompany = ref<number | ''>('')
const searchQuery = ref('') // <-- Search bar state

const showTagModal = ref(false)
const taggingAsset = ref<TaggingAsset | null>(null)
const qrCodeDataUrl = ref<string>('')
const captureRef = ref<HTMLElement | null>(null)

const form = ref<AssetForm>({
  personInCharge: '',
  department: '',
  invoiceNumber: '',
  invoiceDate: '',
  cost: undefined,
  supplier: '',
  modelNumber: '',
  specs: '',
  asset_info: '',
  remarks: '',
  dateDeployed: '',
  categoryId: undefined,
  companyId: undefined,
  is_active: true,
})

const errors = ref<Record<string, string>>({})
const userStore = useUserStore()
const loading = ref(true)


// Status Filter
const statusFilter = ref<'active' | 'inactive' | 'all'>('active');
/* ===== PAGINATION ===== */
const currentPage = ref(1)
const itemsPerPage = ref(10)

const filteredAssets = computed(() => {
  const query = searchQuery.value.trim().toLowerCase()

  return userStore.assets.filter(asset => {
    // Status filter
    if (statusFilter.value === 'active' && !asset.is_active) return false
    if (statusFilter.value === 'inactive' && asset.is_active) return false

    // Category filter
    if (selectedCategory.value !== '' && asset.category_id !== selectedCategory.value) return false

    // Company filter
    if (selectedCompany.value !== '' && asset.company_id !== selectedCompany.value) return false

    // Search filter
    if (
      query &&
      !asset.person_in_charge?.toLowerCase().includes(query) &&
      !asset.company?.name?.toLowerCase().includes(query)
    ) {
      return false
    }

    return true
  })
})

const paginatedAssets = computed(() => {
  const start = (currentPage.value - 1) * itemsPerPage.value
  const end = start + itemsPerPage.value
  return filteredAssets.value.slice(start, end)
})

const totalPages = computed(() => Math.ceil(filteredAssets.value.length / itemsPerPage.value))

watch([selectedCategory, selectedCompany, searchQuery], () => {
  currentPage.value = 1
})

/* ===== HELPERS ===== */
const emptyForm = (): AssetForm => ({
  personInCharge: '',
  department: '',
  invoiceNumber: '',
  invoiceDate: '',
  cost: undefined,
  supplier: '',
  modelNumber: '',
  specs: '',
  asset_info: '',
  remarks: '',
  dateDeployed: '',
  categoryId: undefined,
  companyId: undefined,
  is_active: true,
})

const mapFormToPayload = (f: AssetForm) => ({
  person_in_charge: f.personInCharge,
  department: f.department,
  invoice_number: f.invoiceNumber,
  invoice_date: f.invoiceDate,
  cost: f.cost !== undefined && f.cost !== null ? Number(f.cost) : null,
  supplier: f.supplier,
  model_number: f.modelNumber,
  specs: f.specs,
  asset_info: f.asset_info,
  remarks: f.remarks,
  date_returned: f.dateReturned,
  date_deployed: f.dateDeployed,
  category_id: f.categoryId,
  company_id: f.companyId,
  is_active: f.is_active,
})

/* ===== VALIDATION ===== */
const validateForm = () => {
  errors.value = {}

  if (!(form.value.personInCharge ?? '').trim()) errors.value.personInCharge = 'Person In-charge is required'
  if (!(form.value.department ?? '').trim()) errors.value.department = 'Department is required'
  if (!(form.value.invoiceNumber ?? '').trim()) errors.value.invoiceNumber = 'Invoice Number is required'
  if (!form.value.invoiceDate) errors.value.invoiceDate = 'Invoice Date is required'
  if (form.value.cost === undefined || form.value.cost === null || form.value.cost <= 0) errors.value.cost = 'Cost must be greater than 0'
  if (!(form.value.supplier ?? '').trim()) errors.value.supplier = 'Supplier is required'
  if (!(form.value.modelNumber ?? '').trim()) errors.value.modelNumber = 'Model Number is required'
  if (!form.value.companyId) errors.value.companyId = 'Company is required'
  if (!form.value.categoryId) errors.value.categoryId = 'Category is required'
  if (!(form.value.specs ?? '').trim()) errors.value.specs = 'Specification is required'
  if (!form.value.dateDeployed) errors.value.dateDeployed = 'Date Deployed is required'

  return Object.keys(errors.value).length === 0
}

/* ===== ACTIONS ===== */
const resetFilters = () => {
  selectedCategory.value = ''
  selectedCompany.value = ''
  searchQuery.value = '' // <-- clear search too
}

const openCreateModal = () => {
  isEditing.value = false
  editingAssetId.value = null
  form.value = emptyForm()
  errors.value = {}
  showCreateModal.value = true
}

const openEditModal = (asset: Asset) => {
  isEditing.value = true
  editingAssetId.value = asset.id
  form.value = {
    personInCharge: asset.person_in_charge,
    department: asset.department,
    invoiceNumber: asset.invoice_number,
    invoiceDate: asset.invoice_date,
    cost: asset.cost,
    supplier: asset.supplier,
    modelNumber: asset.model_number,
    specs: asset.specs,
    asset_info: asset.asset_info,
    remarks: asset.remarks,
    dateDeployed: asset.date_deployed,
    dateReturned: asset.date_returned,
    categoryId: asset.category_id,
    companyId: asset.company_id,
    is_active: asset.is_active, 
  }
  errors.value = {}
  showCreateModal.value = true
}

const submitForm = async () => {
  if (!validateForm()) {
    Swal.fire({ icon: 'error', title: 'Validation Error', text: 'Please fix the highlighted fields.' })
    return
  }

  try {
    const payload = mapFormToPayload(form.value)
    if (isEditing.value && editingAssetId.value) {
      await api.put(`/assets/${editingAssetId.value}`, payload)
      Swal.fire({ icon: 'success', title: 'Updated Successfully!', timer: 2000, showConfirmButton: false })
    } else {
      await api.post('/assets', payload)
      Swal.fire({ icon: 'success', title: 'Created Successfully!', timer: 2000, showConfirmButton: false })
    }
    showCreateModal.value = false
    form.value = emptyForm()
    errors.value = {}
    isEditing.value = false
    editingAssetId.value = null
    await userStore.fetchAssets()
  } catch (err: any) {
    console.error('Failed to submit asset', err)
    
    let errorMessage = 'Failed to submit asset. Please try again.'
    
    if (err.response?.data?.message) {
      errorMessage = err.response.data.message
    } else if (err.response?.status === 500 && err.response?.data) {
      const errorText = JSON.stringify(err.response.data)
      if (errorText.includes('foreign key constraint') || errorText.includes('company_id')) {
        errorMessage = 'Selected company does not exist. Please refresh and try again.'
      } else if (errorText.includes('category_id')) {
        errorMessage = 'Selected category does not exist. Please refresh and try again.'
      }
    }
    
    Swal.fire({ icon: 'error', title: 'Oops...', text: errorMessage })
  }
}

watch(form, () => { errors.value = {} }, { deep: true })

const deleteAsset = async (asset: Asset) => {
  const result = await Swal.fire({
    title: 'Are you sure?',
    text: `Delete asset for ${asset.person_in_charge}?`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Yes, delete it!',
  })
  if (result.isConfirmed) {
    try {
      await api.delete(`/assets/${asset.id}`)
      await userStore.fetchAssets()
      Swal.fire('Deleted!', 'Asset has been deleted.', 'success')
    } catch (err) {
      console.error('Delete failed', err)
      Swal.fire('Error', 'Failed to delete asset', 'error')
    }
  }
}

const initData = async () => {
  if (!userStore.assets.length) {
    loading.value = true
    await userStore.initializeData()
    loading.value = false
  } else {
    loading.value = false
  }
}

/* ===== TAGGING MODAL ===== */
const openTagModal = async (asset: Asset) => {
  showTagModal.value = true
  try {
    const companyCode = asset.company?.code ?? 'NO-CODE'
    const uniqueNumber = asset.id.toString().padStart(6, '0')
    const assetCode = `${companyCode}-${uniqueNumber}`

    taggingAsset.value = { ...asset, uniqueCode: assetCode }

    const qrText = 
      `Category: ${asset.category?.name ?? 'No Category'}\n` +
      `Company: ${asset.company?.name ?? 'No Company'}\n` +
      `Person In-charge: ${asset.person_in_charge ?? 'Unknown'}\n` +
      `Specs: ${asset.specs ?? '-'}`

    qrCodeDataUrl.value = await QRCode.toDataURL(qrText)
  } catch (err) {
    console.error('QR generation failed', err)
    qrCodeDataUrl.value = ''
  }
}

const downloadImage = async () => {
  if (!captureRef.value || !taggingAsset.value) return;

  try {
    const canvas = await html2canvas(captureRef.value, { scale: 2, backgroundColor: '#ffffff' })
    const dataUrl = canvas.toDataURL('image/png')

    const link = document.createElement('a')
    const companyCode = taggingAsset.value?.company?.code?.replace(/\s+/g, '_') ?? 'NO-CODE'
    const uniqueCode = taggingAsset.value?.uniqueCode ?? 'TAG'
    link.href = dataUrl
    link.download = `${companyCode}_${uniqueCode}.png`
    link.click()

    await api.post('/assets/unique-code', {
      asset_id: taggingAsset.value.id,
      unique_code: taggingAsset.value.uniqueCode,
    })

    Swal.fire({ icon: 'success', title: 'Downloaded & Unique Code Saved!', timer: 1500, showConfirmButton: false })
  } catch (err) {
    console.error('Error capturing or saving:', err)
    Swal.fire({ icon: 'error', title: 'Failed to download or save unique code.' })
  }
}

const getCompanyLogo = (company: Company) => {
  if (!company?.logo) return new URL('../assets/uploads/placeholder.png', import.meta.url).href
  try {
    return new URL(`/public/${company.logo}`, import.meta.url).href
  } catch {
    return new URL('../assets/uploads/placeholder.png', import.meta.url).href
  }
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
        <input v-model="searchQuery" type="text" placeholder="Search by Person In-charge or Company" class="w-full border rounded px-3 py-2 text-sm"/>
      </div>

      <!-- Category -->
      <div class="mb-4">
        <label class="block text-sm font-medium mb-1">Category</label>
        <select v-model="selectedCategory" class="w-full border rounded px-3 py-2">
          <option value="">All Categories</option>
          <option v-for="cat in userStore.categories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
        </select>
      </div>

      <!-- Status  -->
       <div class="mb-4">
          <label class="block text-sm font-medium mb-1">Status</label>
          <select v-model="statusFilter" class="w-full border rounded px-3 py-2 text-sm">
            <option value="all">All</option>
            <option value="active">Active</option>
            <option value="inactive">Inactive</option>
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
        <button v-if="selectedCategory || selectedCompany" @click="resetFilters" class="w-full bg-gray-200 py-2 rounded">Clear Filters</button>
      </div>
    </div>

    <!-- Main Table -->
    <div class="flex-1 overflow-x-auto border border-gray-200 rounded shadow-sm">
      <table class="min-w-full divide-y divide-gray-200 text-sm">
        <thead class="bg-emerald-900 text-white">
          <tr>
            <th class="px-3 py-1 font-semibold w-32">Person In-charge</th>
            <th class="px-3 py-1 font-semibold w-24">Department</th>
            <th class="px-3 py-1 font-semibold w-20">Invoice #</th>
            <th class="px-3 py-1 font-semibold w-20">Invoice Date</th>
            <th class="px-3 py-1 font-semibold w-16">Cost</th>
            <th class="px-3 py-1 font-semibold w-24">Company</th>
            <th class="px-3 py-1 font-semibold w-24">Category</th>
            <th class="px-3 py-1 font-semibold w-20">Date Deployed</th>
            <th class="px-3 py-1 font-semibold w-20">Model #</th>
            <th class="px-3 py-1 font-semibold w-24">Supplier</th>
            <th class="px-3 py-1 font-semibold w-32">Specification</th>
            <th class="px-3 py-1 font-semibold w-20 text-center">Actions</th>
          </tr>
        </thead>
        <tbody class="bg-white divide-y divide-gray-200">
          <tr v-for="asset in paginatedAssets" :key="asset.id" class="hover:bg-emerald-50">
            <td class="px-3 py-1 break-words uppercase">{{ asset.person_in_charge }}</td>
            <td class="px-3 py-1 break-words uppercase">{{ asset.department }}</td>
            <td class="px-3 py-1 break-words uppercase">{{ asset.invoice_number || '-' }}</td>
            <td class="px-3 py-1 break-words uppercase">{{ asset.invoice_date || '-' }}</td>
            <td class="px-3 py-1 whitespace-nowrap">{{ asset.cost ?? '-' }}</td>
            <td class="px-3 py-1 break-words uppercase">{{ asset.company?.name || '-' }}</td>
            <td class="px-3 py-1 break-words uppercase">{{ asset.category?.name || '-' }}</td>
            <td class="px-3 py-1 whitespace-nowrap">{{ asset.date_deployed || '-' }}</td>
            <td class="px-3 py-1 break-words uppercase">{{ asset.model_number || '-' }}</td>
            <td class="px-3 py-1 break-words uppercase">{{ asset.supplier || '-' }}</td>
            <td class="px-3 py-1 break-words uppercase">{{ asset.specs || '-' }}</td>
            <td class="px-3 py-1 text-center whitespace-nowrap justify-center gap-1">
              <button @click="openEditModal(asset)" class="bg-blue-900 hover:bg-blue-600 text-white px-2 py-1 rounded text-sm font-medium me-3" title="Edit">✏️ Edit</button>
              <button @click="deleteAsset(asset)" class="bg-red-900 hover:bg-red-700 text-white px-2 py-1 rounded text-sm font-medium me-3" title="Delete">🗑️ Delete</button>
              <button @click="openTagModal(asset)" class="bg-yellow-600 hover:bg-yellow-900 text-white px-2 py-1 rounded text-sm font-medium" title="Tag">🏷️ Add Tag</button>
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
  <div v-if="showCreateModal" class="fixed inset-0 flex items-center justify-center bg-black/50 z-50" @click.self="showCreateModal = false">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-2xl p-4 max-h-[90vh] overflow-y-auto">
      <h2 class="text-lg font-bold mb-3">{{ isEditing ? 'Edit Asset' : 'Create New Asset' }}</h2>

      <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
        <!-- Person In-charge -->
        <div>
          <label class="block text-sm font-medium mb-1">Person In-charge <span class="text-red-500">*</span></label>
          <input v-model="form.personInCharge" type="text" class="w-full border px-2 py-1 rounded text-sm" :class="errors.personInCharge ? 'border-red-500' : 'border-gray-300'" />
          <p v-if="errors.personInCharge" class="text-xs text-red-500 mt-1">{{ errors.personInCharge }}</p>
        </div>

        <!-- Department -->
        <div>
          <label class="block text-sm font-medium mb-1">Department <span class="text-red-500">*</span></label>
          <input v-model="form.department" type="text" class="w-full border px-2 py-1 rounded text-sm" :class="errors.department ? 'border-red-500' : 'border-gray-300'" />
          <p v-if="errors.department" class="text-xs text-red-500 mt-1">{{ errors.department }}</p>
        </div>

        <!-- Invoice Number -->
        <div>
          <label class="block text-sm font-medium mb-1">Invoice Number <span class="text-red-500">*</span></label>
          <input v-model="form.invoiceNumber" type="text" class="w-full border px-2 py-1 rounded text-sm" :class="errors.invoiceNumber ? 'border-red-500' : 'border-gray-300'" />
          <p v-if="errors.invoiceNumber" class="text-xs text-red-500 mt-1">{{ errors.invoiceNumber }}</p>
        </div>

        <!-- Invoice Date -->
        <div>
          <label class="block text-sm font-medium mb-1">Invoice Date <span class="text-red-500">*</span></label>
          <input v-model="form.invoiceDate" type="date" class="w-full border px-2 py-1 rounded text-sm" :class="errors.invoiceDate ? 'border-red-500' : 'border-gray-300'" />
          <p v-if="errors.invoiceDate" class="text-xs text-red-500 mt-1">{{ errors.invoiceDate }}</p>
        </div>

        <!-- Cost -->
        <div>
          <label class="block text-sm font-medium mb-1">Cost <span class="text-red-500">*</span></label>
          <input v-model.number="form.cost" type="number" step="0.01" class="w-full border px-2 py-1 rounded text-sm" :class="errors.cost ? 'border-red-500' : 'border-gray-300'" />
          <p v-if="errors.cost" class="text-xs text-red-500 mt-1">{{ errors.cost }}</p>
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

        <!-- Specification -->
        <div class="col-span-1 md:col-span-2">
          <label class="block text-sm font-medium mb-1">Specification <span class="text-red-500">*</span></label>
          <textarea v-model="form.specs" rows="3" class="w-full border px-2 py-1 rounded text-sm resize-y" :class="errors.specs ? 'border-red-500' : 'border-gray-300'"></textarea>
          <p v-if="errors.specs" class="text-xs text-red-500 mt-1">{{ errors.specs }}</p>
        </div>

        <!-- Asset info  -->

        <div class="col-span-1 md:col-span-2">
          <label class="block text-sm font-medium mb-1">Asset info</label>
          <textarea v-model="form.asset_info" rows="3" class="w-full border px-2 py-1 rounded text-sm resize-y"></textarea>
        </div>

        <!-- Remarks -->
        <div class="col-span-1 md:col-span-2">
          <label class="block text-sm font-medium mb-1">Remarks</label>
          <textarea v-model="form.remarks" rows="3" class="w-full border px-2 py-1 rounded text-sm resize-y border-gray-300"></textarea>
        </div>

        
      <!-- Date Deployed -->
      <div>
        <label class="block text-sm font-medium mb-1">Date Deployed <span class="text-red-500">*</span></label>
        <input v-model="form.dateDeployed" type="date" class="w-full border px-2 py-1 rounded text-sm" :class="errors.dateDeployed ? 'border-red-500' : 'border-gray-300'" />
        <p v-if="errors.dateDeployed" class="text-xs text-red-500 mt-1">{{ errors.dateDeployed }}</p>
      </div>

      <!-- Date Returned (Edit Mode Only) -->
      <div v-if="isEditing">
        <label class="block text-sm font-medium mb-1">Date Returned</label>
        <input v-model="form.dateReturned" type="date" class="w-full border px-2 py-1 rounded text-sm" />
      </div>
    </div>

      <div class="flex justify-end gap-2 mt-4">
        <button @click="showCreateModal = false" class="px-3 py-1 bg-gray-300 rounded text-sm">Cancel</button>
        <button 
          @click="form.is_active = !form.is_active"
          :class="form.is_active ? 'bg-emerald-600 text-white' : 'bg-red-500 text-white-700'"
          class="px-4 py-1 rounded text-sm font-semibold transition">{{ form.is_active ? 'Active' : 'Inactive' }}</button>
        <button @click="submitForm" class="px-3 py-1 bg-emerald-600 text-white rounded text-sm">Submit</button>
      </div>
    </div>
  </div>
  <!-- Tagging Modal -->
   <div v-if="showTagModal" class="fixed inset-0 flex items-center justify-center bg-black/50 z-50" @click.self="showTagModal = false">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-md p-6 max-h-[90vh] overflow-y-auto">
      <h2 class="text-lg font-bold mb-4">Asset Tagging</h2>
      <div ref="captureRef" class="flex flex-col items-center gap-4 p-4" style="background-color: #fff;">
        <img v-if="taggingAsset?.company" :src="getCompanyLogo(taggingAsset.company)" alt="Company Logo" class="h-16 object-contain" />
        <h3 class="text-md font-semibold">{{ taggingAsset?.company?.name }}</h3>
        <h4 class="text-md font-semibold">{{ taggingAsset?.uniqueCode }}</h4>
        <img v-if="qrCodeDataUrl" :src="qrCodeDataUrl" alt="QR Code" class="h-32 w-32" />
      </div>
      <div class="flex gap-2 mt-4">
        <button @click="showTagModal = false" class="flex-1 bg-gray-300 text-gray-700 px-4 py-2 rounded">
          Close
        </button>
        <button @click="downloadImage" class="flex-1 bg-emerald-600 text-white px-4 py-2 rounded">
          ⬇️ Download & Save
        </button>
      </div>
    </div>
  </div>
</template>