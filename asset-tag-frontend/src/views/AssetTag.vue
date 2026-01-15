<script setup lang="ts">
import NavBar from '@/components/NavBar.vue'
import { ref, onMounted, getCurrentInstance } from 'vue'

/* ───────── Types ───────── */
interface Category {
  id: number
  name: string
}

interface Company {
  id: number
  name: string
}

/* ───────── State ───────── */
const showCreateModal = ref(false)

const categories = ref<Category[]>([])
const companies = ref<Company[]>([])

const selectedCategory = ref<number | ''>('')
const selectedCompany = ref<number | ''>('')

const emptyForm = () => ({
  personInCharge: '',
  department: '',
  invoiceNumber: '',
  invoiceDate: '',
  cost: null as number | null,
  supplier: '',
  modelNumber: '',
  assetInfo: '',
  specification: '',
  remarks: '',
  dateDeployed: '',
  companyId: ''
})

const form = ref(emptyForm())

/* ───────── Global $api ───────── */
const internalInstance = getCurrentInstance()
const $api = internalInstance?.appContext.config.globalProperties.$api

/* ───────── API Calls ───────── */
const fetchCategories = async () => {
  if (!$api) return
  try {
    const { data } = await $api.get('/categories')
    categories.value = data
  } catch (err) {
    console.error('Failed to fetch categories:', err)
  }
}

const fetchCompanies = async () => {
  if (!$api) return
  try {
    const { data } = await $api.get('/companies')
    companies.value = data
  } catch (err) {
    console.error('Failed to fetch companies:', err)
  }
}

onMounted(() => {
  fetchCategories()
  fetchCompanies()
})

/* ───────── Actions ───────── */
const resetFilters = () => {
  selectedCategory.value = ''
  selectedCompany.value = ''
}

const openCreateModal = () => {
  form.value = emptyForm()
  showCreateModal.value = true
}

const submitForm = async () => {
  if (!$api) return
  try {
    const { data } = await $api.post('/assets', form.value)
    console.log('Asset created:', data)
    showCreateModal.value = false
  } catch (err) {
    console.error('Failed to create asset:', err)
  }
}
</script>


<template>
  <NavBar />

  <!-- Sidebar -->
  <aside
    class="fixed top-20 left-6 w-80 bg-white rounded-2xl shadow-lg border border-gray-100 p-6 space-y-6"
  >
    <div>
      <h2 class="text-lg font-semibold text-gray-800">Asset Filters</h2>
    </div>

    <!-- Category -->
    <div class="space-y-1">
      <label class="text-sm font-medium text-gray-700">Category</label>
      <select
        v-model="selectedCategory"
        class="w-full rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm
               focus:outline-none focus:ring-2 focus:ring-emerald-500"
      >
        <option value="">All Categories</option>
        <option v-for="c in categories" :key="c.id" :value="c.id">
          {{ c.name }}
        </option>
      </select>
    </div>

    <!-- Company -->
    <div class="space-y-1">
      <label class="text-sm font-medium text-gray-700">Company</label>
      <select
        v-model="selectedCompany"
        class="w-full rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm
               focus:outline-none focus:ring-2 focus:ring-emerald-500"
      >
        <option value="">All Companies</option>
        <option v-for="c in companies" :key="c.id" :value="c.id">
          {{ c.name }}
        </option>
      </select>
    </div>

    <!-- Buttons -->
    <div class="pt-4 border-t border-gray-100 space-y-3">
      <button
        @click="openCreateModal"
        class="w-full rounded-xl bg-emerald-600 hover:bg-emerald-700
               text-white py-2.5 font-medium transition"
      >
        + Create New Asset
      </button>

      <button
        v-if="selectedCategory || selectedCompany"
        @click="resetFilters"
        class="w-full rounded-xl bg-gray-100 hover:bg-gray-200
               text-gray-700 py-2.5 text-sm font-medium transition"
      >
        Clear Filters
      </button>
    </div>
  </aside>

  <!-- Main Content -->
  <main class="ml-96 pt-24 p-10">
    <div class="text-center text-gray-400 py-20">
      Asset list will appear here
    </div>
  </main>

  <!-- CREATE ASSET MODAL -->
  <div
    v-if="showCreateModal"
    class="fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4"
    @click.self="showCreateModal = false"
  >
    <div class="bg-white w-full max-w-4xl rounded-xl shadow-xl overflow-hidden">
      <!-- Header -->
      <header class="bg-emerald-600 px-6 py-4 text-white">
        <h3 class="text-lg font-semibold">Create New Asset</h3>
      </header>

      <!-- Body -->
      <section class="p-6 space-y-6">
        <!-- Row 1 -->
        <div class="grid md:grid-cols-3 gap-4">
          <div>
            <label class="text-sm font-medium text-gray-700">Person In-charge</label>
            <input v-model="form.personInCharge" class="input" />
          </div>

          <div>
            <label class="text-sm font-medium text-gray-700">Department</label>
            <input v-model="form.department" class="input" />
          </div>

          <div>
            <label class="text-sm font-medium text-gray-700">Invoice #</label>
            <input v-model="form.invoiceNumber" class="input" />
          </div>
        </div>

        <!-- Row 2 with Company -->
        <div class="grid md:grid-cols-5 gap-4">
        <div>
            <label class="text-sm font-medium text-gray-700">Invoice Date</label>
            <input type="date" v-model="form.invoiceDate" class="input" />
        </div>

        <div>
            <label class="text-sm font-medium text-gray-700">Cost</label>
            <input type="number" v-model.number="form.cost" class="input" />
        </div>

        <div>
            <label class="text-sm font-medium text-gray-700">Supplier</label>
            <input v-model="form.supplier" class="input" />
        </div>

        <div>
            <label class="text-sm font-medium text-gray-700">Model #</label>
            <input v-model="form.modelNumber" class="input" />
        </div>

        <div>
            <label class="text-sm font-medium text-gray-700">Company</label>
            <select v-model="form.companyId" class="input">
            <option value="">Select Company</option>
            <option v-for="company in companies" :key="company.id" :value="company.id">
                {{ company.name }}
            </option>
            </select>
        </div>
        </div>

        <!-- Row 3 -->
        <div class="grid md:grid-cols-3 gap-4">
          <div>
            <label class="text-sm font-medium text-gray-700">Asset Info</label>
            <textarea v-model="form.assetInfo" class="textarea"></textarea>
          </div>

          <div>
            <label class="text-sm font-medium text-gray-700">Specification</label>
            <textarea v-model="form.specification" class="textarea"></textarea>
          </div>

          <div>
            <label class="text-sm font-medium text-gray-700">Remarks</label>
            <textarea v-model="form.remarks" class="textarea"></textarea>
          </div>
        </div>

        <!-- Date Deployed -->
        <div class="max-w-xs">
          <label class="text-sm font-medium text-gray-700">Date Deployed</label>
          <input type="date" v-model="form.dateDeployed" class="input" />
        </div>
      </section>

      <!-- Footer -->
      <footer class="px-6 py-4 border-t flex justify-end gap-3">
        <button
          class="rounded-lg bg-gray-100 hover:bg-gray-200
                 text-gray-700 px-5 py-2.5 font-medium transition"
          @click="showCreateModal = false"
        >
          Cancel
        </button>

        <button
          class="rounded-lg bg-emerald-600 hover:bg-emerald-700
                 text-white px-6 py-2.5 font-medium transition"
          @click="submitForm"
        >
          Add
        </button>
      </footer>
    </div>
  </div>
</template>

<style scoped>
.input {
  width: 100%;
  border-radius: 0.5rem;
  border: 1px solid #d1d5db;
  padding: 0.5rem 1rem;
}

.input:focus {
  outline: none;
  box-shadow: 0 0 0 2px rgb(16 185 129 / 0.5);
}

.textarea {
  width: 100%;
  min-height: 100px;
  border-radius: 0.5rem;
  border: 1px solid #d1d5db;
  padding: 0.5rem 1rem;
  resize: vertical;
}

.textarea:focus {
  outline: none;
  box-shadow: 0 0 0 2px rgb(16 185 129 / 0.5);
}
</style>
