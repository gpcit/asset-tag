<script setup lang="ts">
import { ref, onMounted, watch } from 'vue'
import Swal from 'sweetalert2'
import api from '@/services/api'
import NavBar from '@/components/NavBar.vue'

interface Employee {
  id: number
  name: string
  department: string
  is_active: number
}

const employees = ref<Employee[]>([])
const showModal = ref(false)
const isEditing = ref(false)
const searchQuery = ref('')
const currentPage = ref(1)
const perPage = ref(10)
const totalPages = ref(1)
const loading = ref(false)

const form = ref({
  id: null as number | null,
  name: '',
  department: '',
  is_active: 1
})

// Fetch employees with search & pagination
const fetchEmployees = async (query = '', page = 1) => {
  try {
    loading.value = true
    const res = await api.get('/employees', { params: { q: query, page, perPage: perPage.value } })
    employees.value = res.data.data
    currentPage.value = res.data.current_page
    totalPages.value = res.data.last_page
  } catch (err) {
    Swal.fire('Error', 'Failed to fetch employees', 'error')
  } finally {
    loading.value = false
  }
}

// Debounce search
let searchTimeout: ReturnType<typeof setTimeout>
watch(searchQuery, (val) => {
  clearTimeout(searchTimeout)
  searchTimeout = setTimeout(() => {
    currentPage.value = 1
    fetchEmployees(val, currentPage.value)
  }, 300)
})

// Pagination
const goToPage = (page: number) => {
  if (page < 1 || page > totalPages.value) return
  currentPage.value = page
  fetchEmployees(searchQuery.value, page)
}

// Modal handlers
const openAddModal = () => {
  isEditing.value = false
  form.value = { id: null, name: '', department: '', is_active: 1 }
  showModal.value = true
}

const openEditModal = (emp: Employee) => {
  isEditing.value = true
  form.value = { ...emp }
  showModal.value = true
}

const closeModal = () => {
  showModal.value = false
}

// Save (Add / Edit)
const saveEmployee = async () => {
  if (!form.value.name || !form.value.department) {
    Swal.fire('Warning', 'Name and Department are required', 'warning')
    return
  }

  try {
    if (isEditing.value && form.value.id) {
      await api.put(`/employees/${form.value.id}`, form.value)
      Swal.fire('Success', 'Employee updated successfully', 'success')
    } else {
      await api.post('/employees', form.value)
      Swal.fire('Success', 'Employee added successfully', 'success')
    }
    showModal.value = false
    fetchEmployees(searchQuery.value, currentPage.value)
  } catch (err: any) {
    Swal.fire('Error', err.response?.data?.message || 'Failed to save employee', 'error')
  }
}

// Activate / Deactivate
const toggleStatus = async (emp: Employee) => {
  try {
    await api.patch(`/employees/${emp.id}`, { is_active: emp.is_active ? 0 : 1 })
    Swal.fire('Success', `Employee ${emp.is_active ? 'deactivated' : 'activated'}`, 'success')
    fetchEmployees(searchQuery.value, currentPage.value)
  } catch {
    Swal.fire('Error', 'Failed to update status', 'error')
  }
}

// Delete
const deleteEmployee = async (emp: Employee) => {
  const result = await Swal.fire({
    title: `Delete ${emp.name}?`,
    text: "This action cannot be undone.",
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Yes, delete it!',
    cancelButtonText: 'Cancel'
  })

  if (result.isConfirmed) {
    try {
      await api.delete(`/employees/${emp.id}`)
      Swal.fire('Deleted!', `${emp.name} has been deleted.`, 'success')
      fetchEmployees(searchQuery.value, currentPage.value)
    } catch {
      Swal.fire('Error', 'Failed to delete employee', 'error')
    }
  }
}

// Initial fetch
onMounted(() => fetchEmployees())
</script>

<template>
  <NavBar />

  <div class="max-w-6xl mx-auto mt-8 bg-white shadow-lg rounded-xl p-6">

    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-6 gap-4">
      <h2 class="text-2xl font-bold text-gray-800">👥 Employee Management</h2>

      <div class="flex flex-col sm:flex-row gap-2">
        <!-- Search -->
        <input
          v-model="searchQuery"
          placeholder="Search by name or department"
          class="border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
        />

        <!-- Add Employee -->
        <button
          class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition"
          @click="openAddModal"
        >
          + Add Employee
        </button>
      </div>
    </div>

    <!-- Employee Table -->
    <div class="overflow-x-auto border border-gray-200 rounded-lg">
      <table class="w-full text-left border-collapse">
        <thead class="bg-gray-100 text-sm text-gray-600 uppercase">
          <tr>
            <th class="px-4 py-3">Name</th>
            <th class="px-4 py-3">Department</th>
            <th class="px-4 py-3">Status</th>
            <th class="px-4 py-3 text-center">Actions</th>
          </tr>
        </thead>

        <tbody class="divide-y divide-gray-200">
          <tr v-for="emp in employees" :key="emp.id" class="hover:bg-gray-50">
            <td class="px-4 py-3 font-medium">{{ emp.name }}</td>
            <td class="px-4 py-3">{{ emp.department }}</td>
            <td class="px-4 py-3">
              <span
                class="px-3 py-1 rounded-full text-sm font-semibold"
                :class="emp.is_active ? 'bg-green-100 text-green-700' : 'bg-gray-200 text-gray-600'"
              >
                {{ emp.is_active ? 'Active' : 'Inactive' }}
              </span>
            </td>
            <td class="px-4 py-3">
              <div class="flex justify-center gap-2">
                <button
                  class="p-2 rounded-lg bg-blue-100 text-blue-600 hover:bg-blue-200 transition"
                  title="Edit"
                  @click="openEditModal(emp)"
                >✏️</button>

                <button
                  v-if="emp.is_active"
                  class="p-2 rounded-lg bg-red-100 text-red-600 hover:bg-red-200 transition"
                  title="Deactivate"
                  @click="toggleStatus(emp)"
                >🚫</button>

                <button
                  v-else
                  class="p-2 rounded-lg bg-green-100 text-green-600 hover:bg-green-200 transition"
                  title="Activate"
                  @click="toggleStatus(emp)"
                >♻️</button>

                <button
                  class="p-2 rounded-lg bg-gray-200 text-gray-700 hover:bg-gray-300 transition"
                  title="Delete"
                  @click="deleteEmployee(emp)"
                >🗑️</button>
              </div>
            </td>
          </tr>

          <tr v-if="employees.length === 0">
            <td colspan="4" class="text-center py-6 text-gray-500">
              No employees found
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <div class="flex justify-end items-center gap-4 mt-4 text-gray-700">
      <button
        @click="goToPage(currentPage-1)"
        :disabled="currentPage <= 1 || loading"
        class="px-3 py-1 border rounded-lg hover:bg-gray-100 disabled:opacity-50"
      >Prev</button>

      <span>Page {{ currentPage }} / {{ totalPages }}</span>

      <button
        @click="goToPage(currentPage+1)"
        :disabled="currentPage >= totalPages || loading"
        class="px-3 py-1 border rounded-lg hover:bg-gray-100 disabled:opacity-50"
      >Next</button>
    </div>

  </div>

  <!-- Add / Edit Modal -->
  <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
    <div class="bg-white rounded-xl w-full max-w-md p-6 shadow-lg">
      <h3 class="text-xl font-bold mb-4">{{ isEditing ? 'Edit Employee' : 'Add Employee' }}</h3>

      <div class="space-y-4">
        <div>
          <label class="block text-sm font-medium mb-1">Name</label>
          <input
            v-model="form.name"
            type="text"
            class="w-full border rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
          />
        </div>

        <div>
          <label class="block text-sm font-medium mb-1">Department</label>
          <input
            v-model="form.department"
            type="text"
            class="w-full border rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
          />
        </div>

        <div v-if="isEditing">
          <label class="block text-sm font-medium mb-1">Status</label>
          <select v-model="form.is_active" class="w-full border rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
            <option :value="1">Active</option>
            <option :value="0">Inactive</option>
          </select>
        </div>
      </div>

      <div class="flex justify-end gap-2 mt-6">
        <button class="px-4 py-2 rounded-lg bg-gray-200 hover:bg-gray-300 transition" @click="closeModal">Cancel</button>
        <button class="px-4 py-2 rounded-lg bg-blue-600 text-white hover:bg-blue-700 transition" @click="saveEmployee">Save</button>
      </div>
    </div>
  </div>
</template>
