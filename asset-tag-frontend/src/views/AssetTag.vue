<script setup lang="ts">
import NavBar from '@/components/NavBar.vue'
import { ref, computed, watch } from 'vue'
import Swal from 'sweetalert2'
import { useUserStore } from '@/stores/user'
import api from '@/services/api'
import QRCode from 'qrcode'
import html2canvas from 'html2canvas'

/*  Types  */
interface Category { id: number; name: string }
interface Company { id: number; name: string,  logo?: string | null, code?: string; }

interface AssetForm {
  personInCharge: string
  department: string
  invoiceNumber?: string
  invoiceDate?: string
  cost?: number
  supplier?: string
  modelNumber?: string
  specification?: string
  remarks?: string
  dateDeployed?: string
  categoryId?: number
  companyId?: number
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
  specifications?: string
  remarks?: string
  date_deployed?: string
  category_id?: number
  company_id?: number
  company?: Company
  category?: Category
  uniqueCode?: string
}

/*  State  */
const showCreateModal = ref(false)
const isEditing = ref(false)
const editingAssetId = ref<number | null>(null)

const selectedCategory = ref<number | ''>('')
const selectedCompany = ref<number | ''>('')

const showTagModal = ref(false)
const taggingAsset = ref<Asset | null>(null)
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
  specification: '',
  remarks: '',
  dateDeployed: '',
  categoryId: undefined,
  companyId: undefined,
})
const errors = ref<Record<string, string>>({})
const userStore = useUserStore()
const loading = ref(true)

/*  Computed filtered assets  */
const filteredAssets = computed(() => {
  return userStore.assets.filter(asset => {
    const matchesCategory =
      selectedCategory.value === '' || asset.category_id === selectedCategory.value
    const matchesCompany =
      selectedCompany.value === '' || asset.company_id === selectedCompany.value
    return matchesCategory && matchesCompany
  })
})

/*  Helpers  */
const emptyForm = (): AssetForm => ({
  personInCharge: '',
  department: '',
  invoiceNumber: '',
  invoiceDate: '',
  cost: undefined,
  supplier: '',
  modelNumber: '',
  specification: '',
  remarks: '',
  dateDeployed: '',
  categoryId: undefined,
  companyId: undefined,
})

const mapFormToPayload = (f: AssetForm) => ({
  person_in_charge: f.personInCharge,
  department: f.department,
  invoice_number: f.invoiceNumber,
  invoice_date: f.invoiceDate,
  cost: f.cost,
  supplier: f.supplier,
  model_number: f.modelNumber,
  specifications: f.specification,
  remarks: f.remarks,
  date_deployed: f.dateDeployed,
  category_id: f.categoryId,
  company_id: f.companyId,
})

const validateForm = () => {
  errors.value = {}

  if (!form.value.personInCharge.trim()) {
    errors.value.personInCharge = 'Person In-charge is required'
  }

  if (!form.value.department.trim()) {
    errors.value.department = 'Department is required'
  }

  if (!(form.value.invoiceNumber ?? '').trim()) {
    errors.value.invoiceNumber = 'Invoice Number is required'
  }

  if (!form.value.invoiceDate) {
    errors.value.invoiceDate = 'Invoice Date is required'
  }

  if (form.value.cost === undefined || form.value.cost <= 0) {
    errors.value.cost = 'Cost must be greater than 0'
  }

  if (!(form.value.supplier ?? '').trim()) {
    errors.value.supplier = 'Supplier is required'
  }

  if (!(form.value.modelNumber ?? '').trim()) {
  errors.value.modelNumber = 'Model Number is required'
  }

  if (!form.value.companyId) {
    errors.value.companyId = 'Company is required'
  }

  if (!form.value.categoryId) {
    errors.value.categoryId = 'Category is required'
  }

  if (!(form.value.specification ?? '').trim()) {
  errors.value.specification = 'Specification is required'
  }


  if (!form.value.dateDeployed) {
    errors.value.dateDeployed = 'Date Deployed is required'
  }

  return Object.keys(errors.value).length === 0
}


/*  Actions  */
const resetFilters = () => {
  selectedCategory.value = ''
  selectedCompany.value = ''
}

const openCreateModal = () => {
  isEditing.value = false
  editingAssetId.value = null
  form.value = emptyForm()
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
    specification: asset.specifications,
    remarks: asset.remarks,
    dateDeployed: asset.date_deployed,
    categoryId: asset.category_id,
    companyId: asset.company_id,
  }
  showCreateModal.value = true
}

const submitForm = async () => {
  if (!validateForm()) {
    Swal.fire({
      icon: 'error',
      title: 'Validation Error',
      text: 'Please fix the highlighted fields.'
    })
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
    isEditing.value = false
    editingAssetId.value = null
    await userStore.fetchAssets() // refresh store data

  } catch (err: any) {
    console.error('Failed to submit asset', err)
    if (err.response?.data?.errors) {
      const messages = Object.values(err.response.data.errors).flat().join('\n')
      Swal.fire({ icon: 'error', title: 'Validation Error', text: messages })
      return
    }
    Swal.fire({ icon: 'error', title: 'Oops...', text: 'Failed to submit asset. Please try again.' })
  }
}

/* ================= CLEAR ERRORS ON CHANGE ================= */
watch(form, () => {
  errors.value = {}
}, { deep: true })

const deleteAsset = async (asset: Asset) => {
  const result = await Swal.fire({
    title: 'Are you sure?',
    text: `Delete asset ${asset.person_in_charge}?`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Yes, delete it!',
  })

  if (result.isConfirmed) {
    try {
      await api.delete(`/assets/${asset.id}`)
      await userStore.fetchAssets() // refresh store
      Swal.fire('Deleted!', 'Asset has been deleted.', 'success')
    } catch (err) {
      console.error('Delete failed', err)
      Swal.fire('Error', 'Failed to delete asset', 'error')
    }
  }
}

/*  Initial data load after login  */
const initData = async () => {
  if (!userStore.assets.length) {
    loading.value = true
    await userStore.initializeData() // fetch assets, categories, companies
    loading.value = false
  }
}

const openTagModal = async (asset: Asset) => {
  showTagModal.value = true

  try {
    // Generate unique code for display
    const companyCode = asset.company?.code ?? 'NO-CODE'
    const uniqueNumber = asset.id.toString().padStart(6, '0')
    const assetCode = `${companyCode}-${uniqueNumber}`

    // Assign it to the taggingAsset
    taggingAsset.value = { ...asset, uniqueCode: assetCode }

    // Generate QR code content with detailed info
    const qrText = 
      `Category: ${asset.category?.name ?? 'No Category'}\n` +
      `Company: ${asset.company?.name ?? 'No Company'}\n` +
      `Person In-charge: ${asset.person_in_charge ?? 'Unknown'}`

    qrCodeDataUrl.value = await QRCode.toDataURL(qrText)
  } catch (err) {
    console.error('QR code generation failed', err)
    qrCodeDataUrl.value = ''
  }
}

/* Download QR code */
const downloadImage = async () => {
  if (!captureRef.value || !taggingAsset.value?.company?.code) return

  try {
    const canvas = await html2canvas(captureRef.value, {
      scale: 2,
      backgroundColor: '#ffffff',
    })

    const dataUrl = canvas.toDataURL('image/png')

    const link = document.createElement('a')
    link.href = dataUrl

    // Use company code as filename
    const companyCode = taggingAsset.value.company.code.replace(/\s+/g, '_')
    const uniqueCode = taggingAsset.value.uniqueCode ?? 'tag'

    link.download = `${companyCode}_${uniqueCode}.png`
    link.click()
  } catch (err) {
    console.error('Error capturing:', err)
  }
}


/* Get company logo from assets/uploads folder */
const getCompanyLogo = (company: Company) => {
  if (!company?.logo) {
    return new URL('../assets/uploads/placeholder.png', import.meta.url).href
  }

  try {
    return new URL(
      `/public/${company.logo}`,  // Use 'logo' here
      import.meta.url
    ).href
  } catch (err) {
    console.warn(`Logo not found for company ${company.name}`)
    return new URL('../assets/uploads/placeholder.png', import.meta.url).href
  }
}

initData()
</script>

<template>
  <NavBar />

  <div class="flex gap-6 p-4 pt-20 items-start">
    <!-- Sidebar / Filters -->
    <div class="w-80 p-6 bg-white shadow-xl rounded-xl flex-shrink-0">
      <h3 class="text-lg font-semibold mb-2">Filter Assets</h3>

      <!-- Category -->
      <div class="mb-4">
        <label class="block text-sm font-medium mb-1">Category</label>
        <select v-model="selectedCategory" class="w-full border rounded px-3 py-2">
          <option value="">All Categories</option>
          <option v-for="cat in userStore.categories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
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
          <tr v-for="asset in filteredAssets" :key="asset.id" class="hover:bg-emerald-50">
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
            <td class="px-3 py-1 break-words uppercase">{{ asset.specifications || '-' }}</td>
            <td class="px-3 py-1 text-center whitespace-nowrap justify-center gap-1">
              <button @click="openEditModal(asset)" class="bg-blue-900 hover:bg-blue-600 text-white px-2 py-1 rounded text-sm font-medium me-3" title="Edit">✏️ Edit</button>
              <button @click="deleteAsset(asset)" class="bg-red-900 hover:bg-red-700 text-white px-2 py-1 rounded text-sm font-medium me-3" title="Delete">🗑️ Delete</button>
              <button @click="openTagModal(asset)" class="bg-yellow-600 hover:bg-yellow-900 text-white px-2 py-1 rounded text-sm font-medium" title="Tag">🏷️ Add Tag</button>
            </td>
          </tr>
        </tbody>
      </table>
      <p v-if="filteredAssets.length === 0" class="text-center text-gray-500 mt-2 py-2">No assets found.</p>
    </div>
  </div>

  <!-- Create/Edit Modal -->
  <div v-if="showCreateModal" class="fixed inset-0 flex items-center justify-center bg-black/50" @click.self="showCreateModal = false">
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
          <textarea v-model="form.specification" rows="3" class="w-full border px-2 py-1 rounded text-sm resize-y" :class="errors.specification ? 'border-red-500' : 'border-gray-300'"></textarea>
          <p v-if="errors.specification" class="text-xs text-red-500 mt-1">{{ errors.specification }}</p>
        </div>

        <!-- Remarks -->
        <div class="col-span-1 md:col-span-2">
          <label class="block text-sm font-medium mb-1">Remarks <span class="text-red-500"></span></label>
          <textarea v-model="form.remarks" rows="3" class="w-full border px-2 py-1 rounded text-sm resize-y border-gray-300"></textarea>
        </div>

        <!-- Date Deployed -->
        <div>
          <label class="block text-sm font-medium mb-1">Date Deployed <span class="text-red-500">*</span></label>
          <input v-model="form.dateDeployed" type="date" class="w-full border px-2 py-1 rounded text-sm" :class="errors.dateDeployed ? 'border-red-500' : 'border-gray-300'" />
          <p v-if="errors.dateDeployed" class="text-xs text-red-500 mt-1">{{ errors.dateDeployed }}</p>
        </div>
      </div>

      <div class="flex justify-end gap-2 mt-4">
        <button @click="showCreateModal = false" class="px-3 py-1 bg-gray-300 rounded text-sm">Cancel</button>
        <button @click="submitForm" class="px-3 py-1 bg-emerald-600 text-white rounded text-sm">Submit</button>
      </div>
    </div>
  </div>

  <!-- Tagging Modal -->
  <div v-if="showTagModal" class="fixed inset-0 flex items-center justify-center bg-black/50" @click.self="showTagModal = false">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-md p-6 max-h-[90vh] overflow-y-auto">
      <h2 class="text-lg font-bold mb-4">Asset Tagging</h2>

      <div ref="captureRef" class="flex flex-col items-center gap-4 p-4" style="background-color: #ffffff;">
        <img v-if="taggingAsset?.company" :src="getCompanyLogo(taggingAsset.company)" alt="Company Logo" class="h-16 object-contain" />
        <h3 class="text-md font-semibold" style="color: #000000;">{{ taggingAsset?.company?.name }}</h3>
        <h4 class="text-md font-semibold" style="color: #000000;">{{ taggingAsset?.uniqueCode }}</h4>
        <img v-if="qrCodeDataUrl" :src="qrCodeDataUrl" alt="QR Code" class="h-32 w-32" />
      </div>

      <button @click="downloadImage" class="mt-4 bg-blue-600 text-white px-4 py-2 rounded">
        ⬇️ Download Image
      </button>
    </div>
  </div>
</template>
