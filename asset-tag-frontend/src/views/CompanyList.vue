<script setup lang="ts">
import { ref, onMounted, computed, watch } from 'vue'
import NavBar from '@/components/NavBar.vue'
import api from '@/services/api'
import Swal from 'sweetalert2'

/* ================= USER ================= */
interface User {
  id?: number
  name?: string
  role?: 'admin' | 'staff'
}

const user = ref<User>(
  JSON.parse(localStorage.getItem('user') || '{}')
)

watch(
  () => localStorage.getItem('user'),
  (val) => {
    if (val) user.value = JSON.parse(val)
  }
)

/* ================= COMPANY ================= */
interface Company {
  id: number
  name: string
  address?: string
  contact_no?: string
  code?: string
}

const companies = ref<Company[]>([])
const search = ref('')
const showAddForm = ref(false)

const newCompany = ref({
  name: '',
  address: '',
  contact_no: '',
  code: ''
})

/* ================= FETCH ================= */
const fetchCompanies = async () => {
  try {
    const res = await api.get<Company[]>('/companies')
    companies.value = res.data
  } catch (err) {
    console.error(err)
    Swal.fire('Error', 'Failed to load companies', 'error')
  }
}

/* ================= SEARCH ================= */
const filteredCompanies = computed(() => {
  const q = search.value.toLowerCase()
  return companies.value.filter(c =>
    c.name.toLowerCase().includes(q) ||
    c.code?.toLowerCase().includes(q) ||
    c.contact_no?.toLowerCase().includes(q)
  )
})

/* ================= ADD ================= */
const addCompany = async () => {
  if (!newCompany.value.name.trim()) {
    Swal.fire('Error', 'Company name is required', 'error')
    return
  }

  try {
    const res = await api.post<Company>('/companies', newCompany.value)
    companies.value.push(res.data)
    resetForm()
    showAddForm.value = false
    Swal.fire('Success', 'Company added successfully', 'success')
  } catch (err: any) {
    Swal.fire(
      'Error',
      err.response?.data?.message || 'Failed to add company',
      'error'
    )
  }
}

/* ================= DELETE ================= */
const confirmDelete = (id: number, name: string) => {
  Swal.fire({
    title: `Delete "${name}"?`,
    text: "This action can't be undone",
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#dc2626',
    cancelButtonColor: '#6b7280',
    confirmButtonText: 'Yes, delete it'
  }).then(result => {
    if (result.isConfirmed) deleteCompany(id)
  })
}

const deleteCompany = async (id: number) => {
  try {
    await api.delete(`/companies/${id}`)
    companies.value = companies.value.filter(c => c.id !== id)
    Swal.fire('Deleted', 'Company deleted successfully', 'success')
  } catch {
    Swal.fire('Error', 'Failed to delete company', 'error')
  }
}

/* ================= UTILS ================= */
const resetForm = () => {
  newCompany.value = {
    name: '',
    address: '',
    contact_no: '',
    code: ''
  }
}

onMounted(fetchCompanies)
</script>

<template>
  <NavBar />

  <div class="max-w-4xl mx-auto mt-10 bg-white p-6 rounded-xl shadow-lg">
    <!-- Header -->
    <div class="flex justify-between items-center mb-6">
      <h2 class="text-2xl font-semibold text-gray-800">Manage Companies</h2>

      <button
        v-if="user.role === 'admin'"
        @click="showAddForm = !showAddForm"
        class="px-5 py-2 bg-emerald-500 text-white rounded-lg hover:bg-emerald-600 transition"
      >
        + Add Company
      </button>
    </div>

    <!-- Add Company Form -->
    <div
      v-if="showAddForm"
      class="mb-6 p-5 bg-gray-50 rounded-lg border border-gray-200"
    >
      <h3 class="text-lg font-semibold mb-4 text-gray-700">New Company</h3>

      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div class="md:col-span-2">
          <input
            v-model="newCompany.name"
            type="text"
            placeholder="Company Name *"
            class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-emerald-400 focus:outline-none"
          />
        </div>

        <div class="md:col-span-2">
          <input
            v-model="newCompany.address"
            type="text"
            placeholder="Address *"
            class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-emerald-400 focus:outline-none"
          />
        </div>

        <div>
          <input
            v-model="newCompany.contact_no"
            type="text"
            placeholder="Contact Number"
            class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-emerald-400 focus:outline-none"
          />
        </div>

        <div>
          <input
            v-model="newCompany.code"
            type="text"
            placeholder="Company Code *"
            class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-emerald-400 focus:outline-none"
          />
        </div>
      </div>

      <div class="flex gap-3 mt-4">
        <button
          @click="addCompany"
          class="px-5 py-2 bg-emerald-500 text-white rounded-lg hover:bg-emerald-600 transition"
        >
          Save
        </button>
        <button
          @click="showAddForm = false"
          class="px-5 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition"
        >
          Cancel
        </button>
      </div>
    </div>

    <!-- Search -->
    <input
      v-model="search"
      type="text"
      placeholder="Search company..."
      class="w-full mb-4 border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-emerald-400 focus:outline-none"
    />

    <!-- Table -->
    <div class="overflow-x-auto">
      <table class="w-full border border-gray-200 rounded-lg overflow-hidden">
        <thead class="bg-gray-50">
          <tr>
            <th class="px-4 py-3 text-left text-sm font-semibold">Name</th>
            <th class="px-4 py-3 text-left text-sm font-semibold">Code</th>
            <th class="px-4 py-3 text-left text-sm font-semibold">Address</th>
            <th class="px-4 py-3 text-left text-sm font-semibold">Contact</th>
            <th
              v-if="user.role === 'admin'"
              class="px-4 py-3 text-center text-sm font-semibold"
            >
              Action
            </th>
          </tr>
        </thead>

        <tbody class="divide-y divide-gray-200">
          <tr
            v-for="c in filteredCompanies"
            :key="c.id"
            class="hover:bg-gray-50"
          >
            <td class="px-4 py-3 font-medium">{{ c.name }}</td>
            <td class="px-4 py-3">{{ c.code }}</td>
            <td class="px-4 py-3 text-sm text-gray-600">{{ c.address || '-' }}</td>
            <td class="px-4 py-3 text-sm text-gray-600">{{ c.contact_no || '-' }}</td>
            <td
              v-if="user.role === 'admin'"
              class="px-4 py-3 text-center"
            >
              <button
                @click="confirmDelete(c.id, c.name)"
                class="px-3 py-1 bg-red-100 text-red-600 rounded hover:bg-red-200 transition text-sm"
              >
                Delete
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <p
      v-if="!filteredCompanies.length"
      class="text-center text-gray-400 mt-6"
    >
      No companies found
    </p>
  </div>
</template>