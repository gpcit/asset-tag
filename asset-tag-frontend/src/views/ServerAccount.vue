<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import Swal from 'sweetalert2'
import NavBar from '@/components/NavBar.vue'
import api from '@/services/api'

interface ServerAccount {
  id?: number
  name: string
  department: string
  server_user: string
  server_password?: string
  status: string
  remarks?: string
  company_id?: number
}

const dataList = ref<ServerAccount[]>([])
const loading = ref(false)
const showModal = ref(false)
const isEditMode = ref(false)
const searchQuery = ref('')
const selectedCompany = ref('')
const companies = ref<{id: number, name: string}[]>([])

// Pagination
const currentPage = ref(1)
const itemsPerPage = ref(10)

// Form data
const formData = ref<ServerAccount>({
  name: '',
  department: '',
  server_user: '',
  server_password: '',
  status: 'Active',
  remarks: '',
})

// For server-side search, filteredData just returns dataList
// (filtering happens on backend)
const filteredData = computed(() => {
  return dataList.value
})

// Computed: Paginated data
const paginatedData = computed(() => {
  const start = (currentPage.value - 1) * itemsPerPage.value
  const end = start + itemsPerPage.value
  return filteredData.value.slice(start, end)
})

// Computed: Total pages
const totalPages = computed(() => {
  return Math.ceil(filteredData.value.length / itemsPerPage.value)
})

// Computed: Visible page numbers
const visiblePages = computed(() => {
  const total = totalPages.value
  const current = currentPage.value
  const pages: (number | string)[] = []
  
  if (total <= 7) {
    // Show all pages if 7 or fewer
    for (let i = 1; i <= total; i++) {
      pages.push(i)
    }
  } else {
    // Always show first page
    pages.push(1)
    
    if (current > 3) {
      pages.push('...')
    }
    
    // Show pages around current page
    const start = Math.max(2, current - 1)
    const end = Math.min(total - 1, current + 1)
    
    for (let i = start; i <= end; i++) {
      pages.push(i)
    }
    
    if (current < total - 2) {
      pages.push('...')
    }
    
    // Always show last page
    pages.push(total)
  }
  
  return pages
})

// Watch search query and fetch from server
const handleSearch = () => {
  fetchData()
}

// Pagination methods
const goToPage = (page: number) => {
  if (page >= 1 && page <= totalPages.value) {
    currentPage.value = page
  }
}

const nextPage = () => {
  if (currentPage.value < totalPages.value) {
    currentPage.value++
  }
}

const prevPage = () => {
  if (currentPage.value > 1) {
    currentPage.value--
  }
}

// Fetch companies for filter dropdown
const fetchCompanies = async () => {
  try {
    const { data } = await api.get('/companies')
    companies.value = data
  } catch (error) {
    console.error('Error fetching companies:', error)
  }
}

// Fetch data from API with search
const fetchData = async () => {
  loading.value = true
  try {
    const params = new URLSearchParams()
    if (searchQuery.value.trim()) {
      params.append('search', searchQuery.value.trim())
    }
    if (selectedCompany.value) {
      params.append('company_id', selectedCompany.value)
    }
    
    const url = params.toString() ? `/servers?${params.toString()}` : '/servers'
    const { data } = await api.get(url)
    dataList.value = data
    currentPage.value = 1 // Reset to first page when fetching new data
  } catch (error) {
    console.error('Error fetching data:', error)
    Swal.fire('Error', 'Failed to load data', 'error')
  } finally {
    loading.value = false
  }
}

// Open modal for creating new account
const openCreateModal = () => {
  isEditMode.value = false
  formData.value = {
    name: '',
    department: '',
    server_user: '',
    server_password: '',
    status: 'Active',
    remarks: '',
  }
  showModal.value = true
}

// Open modal for editing
const openEditModal = (item: ServerAccount) => {
  isEditMode.value = true
  formData.value = {
    id: item.id,
    name: item.name,
    department: item.department,
    server_user: item.server_user,
    server_password: item.server_password, // Don't pre-fill password for security
    status: item.status,
    remarks: item.remarks || '',
  }
  showModal.value = true
}

// Close modal
const closeModal = () => {
  showModal.value = false
  formData.value = {
    name: '',
    department: '',
    server_user: '',
    server_password: '',
    status: 'Active',
    remarks: '',
  }
}

// Save (Create or Update)
const saveAccount = async () => {
  // Validation
  if (!formData.value.name || !formData.value.department || !formData.value.server_user) {
    Swal.fire('Error', 'Please fill in all required fields', 'error')
    return
  }

  try {
    if (isEditMode.value) {
      const { data } = await api.put(`/servers/${formData.value.id}`, formData.value)
      // Update existing item in list
      const index = dataList.value.findIndex(item => item.id === data.id)
      if (index !== -1) {
        dataList.value[index] = data
      }
      Swal.fire('Success', 'Account updated successfully', 'success')
    } else {
      const { data } = await api.post('/servers', formData.value)
      // Add new item to list
      dataList.value.unshift(data)
      Swal.fire('Success', 'Account created successfully', 'success')
    }

    closeModal()
  } catch (error) {
    console.error('Error saving:', error)
    Swal.fire('Error', 'Failed to save account', 'error')
  }
}

// Delete account
const deleteAccount = async (item: ServerAccount) => {
  const result = await Swal.fire({
    title: `Delete ${item.name}?`,
    text: 'This action cannot be undone',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Yes, delete it',
    confirmButtonColor: '#dc2626',
    cancelButtonText: 'Cancel',
  })

  if (result.isConfirmed) {
    try {
      await api.delete(`/servers/${item.id}`)
      dataList.value = dataList.value.filter(i => i.id !== item.id)
      Swal.fire('Deleted!', 'Account deleted successfully', 'success')
    } catch (error) {
      console.error('Error deleting:', error)
      Swal.fire('Error', 'Failed to delete account', 'error')
    }
  }
}

// Load data on mount
onMounted(() => {
  fetchCompanies()
  fetchData()
})
</script>

<template>
  <NavBar />
  <div class="p-6 max-w-7xl mx-auto">
    <!-- Header -->
    <div class="flex justify-between items-center mb-6">
      <h2 class="text-3xl font-bold text-gray-800">Server Accounts</h2>
      <button
        @click="openCreateModal"
        class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition flex items-center gap-2"
      >
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
        </svg>
        Add New Account
      </button>
    </div>

    <!-- Search Bar and Filters -->
    <div class="mb-4 flex gap-3">
      <div class="flex-1 relative">
        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
          <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
          </svg>
        </div>
        <input
          v-model="searchQuery"
          @input="handleSearch"
          type="text"
          class="w-full pl-10 pr-4 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent transition"
          placeholder="Search by name or department"
        />
        <button
          v-if="searchQuery"
          @click="searchQuery = ''; handleSearch()"
          class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-gray-600"
        >
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
          </svg>
        </button>
      </div>
      
      <!-- Company Filter -->
      <div class="w-48">
        <select
          v-model="selectedCompany"
          @change="handleSearch"
          class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-900 focus:border-transparent transition"
        >
          <option value="">All Companies</option>
          <option v-for="company in companies" :key="company.id" :value="company.id">
            {{ company.name }}
          </option>
        </select>
      </div>
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="text-center py-12">
      <div class="inline-block animate-spin rounded-full h-12 w-12 border-b-2 border-green-900"></div>
      <p class="text-gray-500 mt-4">Loading accounts...</p>
    </div>

    <!-- Table -->
    <div v-else class="bg-white rounded-lg shadow-lg overflow-hidden">
      <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Department</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Server User</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Password</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Remarks</th>
              <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
            </tr>
          </thead>
          <tbody class="bg-white divide-y divide-gray-200">
            <tr v-for="item in paginatedData" :key="item.id" class="hover:bg-gray-50 transition">
              <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{{ item.name }}</td>
              <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">{{ item.department }}</td>
              <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">{{ item.server_user }}</td>
              <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600 font-mono">{{ item.server_password || '-' }}</td>
              <td class="px-6 py-4 whitespace-nowrap">
                <span 
                  :class="item.status === 'Active' 
                    ? 'px-3 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800' 
                    : 'px-3 py-1 text-xs font-semibold rounded-full bg-red-100 text-red-800'">
                  {{ item.status }}
                </span>
              </td>
              <td class="px-6 py-4 text-sm text-gray-600">{{ item.remarks || '-' }}</td>
              <td class="px-6 py-4 whitespace-nowrap text-center">
                <div class="flex justify-center gap-2">
                  <button 
                    @click="openEditModal(item)" 
                    class="px-3 py-1.5 bg-blue-700 text-white rounded-md hover:bg-blue-900 transition text-sm font-medium">
                    Edit
                  </button>
                  <button 
                    @click="deleteAccount(item)" 
                    class="px-3 py-1.5 bg-red-700 text-white rounded-md hover:bg-red-900 transition text-sm font-medium">
                    Delete
                  </button>
                </div>
              </td>
            </tr>
            <tr v-if="!paginatedData.length">
              <td colspan="7" class="text-center py-8 text-gray-400">
                <svg class="w-16 h-16 mx-auto mb-2 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
                </svg>
                {{ searchQuery ? 'No accounts match your search.' : 'No accounts found. Click "Add New Account" to get started.' }}
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      
      <!-- Pagination -->
      <div v-if="filteredData.length > 0" class="bg-white px-4 py-3 border-t border-gray-200">
        <div class="flex items-center justify-between text-sm">
          <!-- Items per page -->
          <div class="flex items-center gap-2">
            <select
              v-model="itemsPerPage"
              @change="currentPage = 1"
              class="px-2 py-1 text-sm border border-gray-300 rounded focus:outline-none focus:ring-1 focus:ring-green-500"
            >
              <option :value="5">5</option>
              <option :value="10">10</option>
              <option :value="25">25</option>
              <option :value="50">50</option>
            </select>
            <span class="text-gray-600">per page</span>
          </div>
          
          <!-- Pagination controls -->
          <div class="flex items-center gap-1">
            <!-- Previous button -->
            <button
              @click="prevPage"
              :disabled="currentPage === 1"
              class="p-1 rounded transition disabled:opacity-30 disabled:cursor-not-allowed hover:bg-gray-100"
            >
              <svg class="w-4 h-4 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
              </svg>
            </button>
            
            <!-- Page numbers -->
            <div class="flex items-center gap-0.5">
              <button
                v-for="(page, index) in visiblePages"
                :key="index"
                @click="typeof page === 'number' ? goToPage(page) : null"
                :disabled="page === '...'"
                :class="page === currentPage
                  ? 'min-w-[1.75rem] px-2 py-1 text-xs rounded bg-green-600 text-white font-medium'
                  : page === '...'
                  ? 'min-w-[1.75rem] px-2 py-1 text-xs text-gray-400 cursor-default'
                  : 'min-w-[1.75rem] px-2 py-1 text-xs rounded text-gray-700 hover:bg-gray-100 transition'"
              >
                {{ page }}
              </button>
            </div>
            
            <!-- Next button -->
            <button
              @click="nextPage"
              :disabled="currentPage === totalPages"
              class="p-1 rounded transition disabled:opacity-30 disabled:cursor-not-allowed hover:bg-gray-100"
            >
              <svg class="w-4 h-4 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
              </svg>
            </button>
          </div>

          <!-- Info -->
          <div class="text-gray-600">
            {{ ((currentPage - 1) * itemsPerPage) + 1 }}-{{ Math.min(currentPage * itemsPerPage, filteredData.length) }} of {{ filteredData.length }}
          </div>
        </div>
      </div>
    </div>

    <!-- Modal -->
    <div v-if="showModal" class="fixed inset-0 flex items-center justify-center z-50 p-4" style="background-color: rgba(0, 0, 0, 0.3); backdrop-filter: blur(4px);">
      <div class="bg-white rounded-xl shadow-2xl max-w-md w-full max-h-[90vh] overflow-y-auto animate-scale-in">
        <!-- Modal Header -->
        <div class="flex justify-between items-center p-6 border-b border-gray-200">
          <h3 class="text-2xl font-bold text-gray-800">
            {{ isEditMode ? 'Edit Account' : 'Create New Account' }}
          </h3>
          <button @click="closeModal" class="text-gray-400 hover:text-gray-600 transition rounded-full p-1 hover:bg-gray-100">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>

        <!-- Modal Body -->
        <div class="p-6 space-y-4">
          <!-- Name -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">
              Name <span class="text-red-500">*</span>
            </label>
            <input
              v-model="formData.name"
              type="text"
              class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent transition"
              placeholder="Enter name"
            />
          </div>

          <!-- Department -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">
              Department <span class="text-red-500">*</span>
            </label>
            <input
              v-model="formData.department"
              type="text"
              class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent transition"
              placeholder="Enter department"
            />
          </div>

          <!-- Server User -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">
              Server User <span class="text-red-500">*</span>
            </label>
            <input
              v-model="formData.server_user"
              type="text"
              class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent transition"
              placeholder="Enter server user"
            />
          </div>

          <!-- Server Password -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">
              Server Password
              <span v-if="isEditMode" class="text-gray-500 text-xs font-normal">(leave blank to keep current)</span>
            </label>
            <input
              v-model="formData.server_password"
              type="password"
              class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent transition"
              placeholder="Enter server password"
            />
          </div>

          <!-- Status -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">
              Status <span class="text-red-500">*</span>
            </label>
            <select
              v-model="formData.status"
              class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent transition"
            >
              <option value="Active">Active</option>
              <option value="Inactive">Inactive</option>
            </select>
          </div>

          <!-- Remarks -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">Remarks</label>
            <textarea
              v-model="formData.remarks"
              rows="3"
              class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent transition resize-none"
              placeholder="Enter remarks (optional)"
            ></textarea>
          </div>
        </div>

        <!-- Modal Footer -->
        <div class="flex justify-end gap-3 p-6 border-t border-gray-200 bg-gray-50 rounded-b-xl">
          <button
            @click="closeModal"
            class="px-5 py-2.5 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-100 transition font-medium"
          >
            Cancel
          </button>
          <button
            @click="saveAccount"
            class="px-5 py-2.5 bg-green-600 text-white rounded-lg hover:bg-green-700 transition font-medium shadow-sm"
          >
            {{ isEditMode ? 'Update' : 'Create' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>