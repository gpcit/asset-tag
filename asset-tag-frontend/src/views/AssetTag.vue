<script setup lang="ts">
import NavBar from '@/components/NavBar.vue'
import { ref, onMounted } from 'vue'
import api from '@/services/api'
import Swal from 'sweetalert2'

interface Category { id: number; name: string }
interface Company { id: number; name: string }

interface AssetForm {
  personInCharge: string
  department: string
  invoiceNumber?: string
  invoiceDate?: string
  cost?: number
  supplier?: string
  modelNumber?: string
  assetInfo?: string
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
  asset_info?: string
  specifications?: string
  remarks?: string
  date_deployed?: string
  category_id?: number
  company_id?: number
  company?: Company
  category?: Category
}

const showModal = ref(false)
const isEditing = ref(false)
const editingAssetId = ref<number | null>(null)

const categories = ref<Category[]>([])
const companies = ref<Company[]>([])
const assets = ref<Asset[]>([])

const selectedCategory = ref<number | ''>('')
const selectedCompany = ref<number | ''>('')

const emptyForm = (): AssetForm => ({
  personInCharge: '',
  department: '',
  invoiceNumber: '',
  invoiceDate: '',
  cost: undefined,
  supplier: '',
  modelNumber: '',
  assetInfo: '',
  specification: '',
  remarks: '',
  dateDeployed: '',
  categoryId: undefined,
  companyId: undefined,
})

const form = ref<AssetForm>(emptyForm())

/* ───────── Fetch Data ───────── */
const fetchCategories = async () => {
  const { data } = await api.get('/categories')
  categories.value = data
}

const fetchCompanies = async () => {
  const { data } = await api.get('/companies')
  companies.value = data
}

const fetchAssets = async () => {
  const { data } = await api.get('/assets')
  assets.value = data
}

onMounted(() => {
  fetchAssets()
  fetchCategories()
  fetchCompanies()
})

/* ───────── Actions ───────── */
const resetFilters = () => {
  selectedCategory.value = ''
  selectedCompany.value = ''
}

const openCreateModal = () => {
  isEditing.value = false
  editingAssetId.value = null
  form.value = emptyForm()
  showModal.value = true
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
    assetInfo: asset.asset_info,
    specification: asset.specifications,
    remarks: asset.remarks,
    dateDeployed: asset.date_deployed,
    categoryId: asset.category_id,
    companyId: asset.company_id,
  }
  showModal.value = true
}

const submitForm = async () => {
  try {
    const payload = { ...form.value }

    if (isEditing.value && editingAssetId.value) {
      await api.put(`/assets/${editingAssetId.value}`, payload)
      Swal.fire({
        icon: 'success',
        title: 'Updated Successfully!',
        timer: 2000,
        timerProgressBar: true,
        showConfirmButton: false,
      })
    } else {
      await api.post('/assets', payload)
      Swal.fire({
        icon: 'success',
        title: 'Created Successfully!',
        timer: 2000,
        timerProgressBar: true,
        showConfirmButton: false,
      })
    }

    showModal.value = false
    form.value = emptyForm()
    isEditing.value = false
    editingAssetId.value = null
    await fetchAssets()
  } catch (err) {
    console.error('Failed to submit asset', err)
    Swal.fire('Error', 'Failed to submit asset', 'error')
  }
}

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
      await fetchAssets()
      Swal.fire('Deleted!', 'Asset has been deleted.', 'success')
    } catch (err) {
      console.error('Delete failed', err)
      Swal.fire('Error', 'Failed to delete asset', 'error')
    }
  }
}
</script>

<template>
  <NavBar />

  <!-- Sidebar / Filter Panel -->
  <div class="fixed top-20 left-6 w-80 p-6 bg-white shadow-xl rounded-xl">
    <h3 class="text-lg font-semibold mb-2">Filter Assets</h3>

    <div class="mb-4">
      <label class="block text-sm font-medium mb-1">Category</label>
      <select v-model="selectedCategory" class="w-full border rounded px-3 py-2">
        <option value="">All Categories</option>
        <option v-for="cat in categories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
      </select>
    </div>

    <div class="mb-4">
      <label class="block text-sm font-medium mb-1">Company</label>
      <select v-model="selectedCompany" class="w-full border rounded px-3 py-2">
        <option value="">All Companies</option>
        <option v-for="comp in companies" :key="comp.id" :value="comp.id">{{ comp.name }}</option>
      </select>
    </div>

    <div class="flex flex-col gap-2">
      <button @click="openCreateModal" class="w-full bg-emerald-600 text-white py-2 rounded">Create New Asset</button>
      <button v-if="selectedCategory || selectedCompany" @click="resetFilters" class="w-full bg-gray-200 py-2 rounded">Clear Filters</button>
    </div>
  </div>

  <!-- Main Content -->
  <div class="ml-96 p-8 pt-24">
    <table class="min-w-full border-collapse border border-gray-300">
      <thead>
        <tr>
          <th class="border border-gray-300 px-3 py-1">Person In-charge</th>
          <th class="border border-gray-300 px-3 py-1">Department</th>
          <th class="border border-gray-300 px-3 py-1">Invoice #</th>
          <th class="border border-gray-300 px-3 py-1">Cost</th>
          <th class="border border-gray-300 px-3 py-1">Company</th>
          <th class="border border-gray-300 px-3 py-1">Category</th>
          <th class="border border-gray-300 px-3 py-1">Date Deployed</th>
          <th class="border border-gray-300 px-3 py-1">Actions</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="asset in assets" :key="asset.id" class="hover:bg-gray-100">
          <td class="border border-gray-300 px-3 py-1">{{ asset.person_in_charge }}</td>
          <td class="border border-gray-300 px-3 py-1">{{ asset.department }}</td>
          <td class="border border-gray-300 px-3 py-1">{{ asset.invoice_number || '-' }}</td>
          <td class="border border-gray-300 px-3 py-1">{{ asset.cost ?? '-' }}</td>
          <td class="border border-gray-300 px-3 py-1">{{ asset.company?.name || '-' }}</td>
          <td class="border border-gray-300 px-3 py-1">{{ asset.category?.name || '-' }}</td>
          <td class="border border-gray-300 px-3 py-1">{{ asset.date_deployed || '-' }}</td>
          <td class="border border-gray-300 px-3 py-1">
            <button @click="openEditModal(asset)" class="text-blue-600 mr-2">Edit</button>
            <button @click="deleteAsset(asset)" class="text-red-600">Delete</button>
          </td>
        </tr>
      </tbody>
    </table>

    <p v-if="assets.length === 0" class="text-center text-gray-500 mt-4">No assets found.</p>
  </div>

  <!-- Create/Edit Modal -->
  <div v-if="showModal" class="fixed inset-0 flex items-center justify-center bg-black/50" @click.self="showModal = false">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-2xl p-4 max-h-[90vh] overflow-y-auto">
      <h2 class="text-lg font-bold mb-3">{{ isEditing ? 'Edit Asset' : 'Create New Asset' }}</h2>

      <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
        <div>
          <label class="block text-sm font-medium mb-1">Person In-charge</label>
          <input v-model="form.personInCharge" type="text" class="w-full border rounded px-2 py-1 text-sm" />
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">Department</label>
          <input v-model="form.department" type="text" class="w-full border rounded px-2 py-1 text-sm" />
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">Invoice Number</label>
          <input v-model="form.invoiceNumber" type="text" class="w-full border rounded px-2 py-1 text-sm" />
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">Invoice Date</label>
          <input v-model="form.invoiceDate" type="date" class="w-full border rounded px-2 py-1 text-sm" />
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">Cost</label>
          <input v-model.number="form.cost" type="number" step="0.01" class="w-full border rounded px-2 py-1 text-sm" />
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">Supplier</label>
          <input v-model="form.supplier" type="text" class="w-full border rounded px-2 py-1 text-sm" />
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">Model Number</label>
          <input v-model="form.modelNumber" type="text" class="w-full border rounded px-2 py-1 text-sm" />
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">Company</label>
          <select v-model="form.companyId" class="w-full border rounded px-2 py-1 text-sm">
            <option value="">Select Company</option>
            <option v-for="comp in companies" :key="comp.id" :value="comp.id">{{ comp.name }}</option>
          </select>
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">Category</label>
          <select v-model="form.categoryId" class="w-full border rounded px-2 py-1 text-sm">
            <option value="">Select Category</option>
            <option v-for="cat in categories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
          </select>
        </div>

        <div class="col-span-1 md:col-span-2">
          <label class="block text-sm font-medium mb-1">Asset Info</label>
          <textarea v-model="form.assetInfo" class="w-full border rounded px-2 py-1 text-sm resize-y" rows="3"></textarea>
        </div>

        <div class="col-span-1 md:col-span-2">
          <label class="block text-sm font-medium mb-1">Specification</label>
          <textarea v-model="form.specification" class="w-full border rounded px-2 py-1 text-sm resize-y" rows="3"></textarea>
        </div>

        <div class="col-span-1 md:col-span-2">
          <label class="block text-sm font-medium mb-1">Remarks</label>
          <textarea v-model="form.remarks" class="w-full border rounded px-2 py-1 text-sm resize-y" rows="3"></textarea>
        </div>

        <div>
          <label class="block text-sm font-medium mb-1">Date Deployed</label>
          <input v-model="form.dateDeployed" type="date" class="w-full border rounded px-2 py-1 text-sm" />
        </div>
      </div>

      <div class="flex justify-end gap-2 mt-4">
        <button @click="showModal = false" class="px-3 py-1 bg-gray-300 rounded text-sm">Cancel</button>
        <button @click="submitForm" class="px-3 py-1 bg-emerald-600 text-white rounded text-sm">Submit</button>
      </div>
    </div>
  </div>
</template>
