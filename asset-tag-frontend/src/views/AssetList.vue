<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import api from '@/services/api';
import Swal from 'sweetalert2';
import NavBar from '@/components/NavBar.vue';

// Data
const assets = ref<any[]>([]);
const loading = ref(true);
const error = ref<string | null>(null);
const search = ref('');

// Sorting
const sortKey = ref<'person_in_charge' | 'company'>('person_in_charge');
const sortAsc = ref(true);

// Pagination
const currentPage = ref(1);
const itemsPerPage = ref(10);

// Status Filter
const statusFilter = ref<'active' | 'inactive' | 'all'>('active');

// Detail Modal
const showDetailModal = ref(false);
const selectedAsset = ref<any>(null);
const loadingDetail = ref(false);
const isEditMode = ref(false);
const editForm = ref<any>({});

// Fetch assets
const fetchAssets = async () => {
  try {
    loading.value = true;
    error.value = null;
    const response = await api.get('/asset_list_all'); // Changed endpoint
    console.log('Asset list response:', response.data);
    assets.value = response.data || [];
  } catch (err: any) {
    console.error('Error fetching asset list:', err);
    error.value = err.message || 'Failed to load assets';
  } finally {
    loading.value = false;
  }
};

// Fetch full asset details
const fetchAssetDetails = async (assetId: number) => {
  try {
    loadingDetail.value = true;
    const response = await api.get(`/assets/${assetId}`);
    return response.data;
  } catch (err: any) {
    console.error('Error fetching asset details:', err);
    Swal.fire({
      icon: 'error',
      title: 'Error',
      text: 'Failed to load asset details. Please try again.'
    });
    return null;
  } finally {
    loadingDetail.value = false;
  }
};

onMounted(fetchAssets);

// Computed: filter and sort
const filteredAssets = computed(() => {
  let filtered = assets.value
    .filter(a => {
      // Filter by status
      if (statusFilter.value === 'active') {
        return a.is_active === 1 || a.is_active === true;
      } else if (statusFilter.value === 'inactive') {
        return a.is_active === 0 || a.is_active === false;
      }
      // 'all' shows everything
      return true;
    })
    .filter(a => {
      const companyName = a.company ?? '';
      const personInCharge = a.person_in_charge ?? '';
      const searchLower = search.value.toLowerCase();
      return (
        personInCharge.toLowerCase().includes(searchLower) ||
        companyName.toLowerCase().includes(searchLower)
      );
    });
  
  filtered.sort((a, b) => {
    let aKey = sortKey.value === 'company' ? (a.company ?? '') : (a.person_in_charge ?? '');
    let bKey = sortKey.value === 'company' ? (b.company ?? '') : (b.person_in_charge ?? '');
    if (aKey < bKey) return sortAsc.value ? -1 : 1;
    if (aKey > bKey) return sortAsc.value ? 1 : -1;
    return 0;
  });
  
  return filtered;
});

// Pagination computed
const totalPages = computed(() => Math.ceil(filteredAssets.value.length / itemsPerPage.value));

const paginatedAssets = computed(() => {
  const start = (currentPage.value - 1) * itemsPerPage.value;
  const end = start + itemsPerPage.value;
  return filteredAssets.value.slice(start, end);
});

// Toggle sorting
const setSort = (key: 'person_in_charge' | 'company') => {
  if (sortKey.value === key) {
    sortAsc.value = !sortAsc.value;
  } else {
    sortKey.value = key;
    sortAsc.value = true;
  }
};

// Pagination methods
const goToPage = (page: number) => {
  if (page >= 1 && page <= totalPages.value) {
    currentPage.value = page;
  }
};

// Reset to page 1 when search changes
const handleSearch = () => {
  currentPage.value = 1;
};

// Change items per page
const changeItemsPerPage = (value: number) => {
  itemsPerPage.value = value;
  currentPage.value = 1;
};

// Open detail modal
const openDetailModal = async (asset: any) => {
  showDetailModal.value = true;
  selectedAsset.value = null;
  isEditMode.value = false;
  
  // Fetch full asset details
  const fullAsset = await fetchAssetDetails(asset.id);
  if (fullAsset) {
    selectedAsset.value = fullAsset;
    editForm.value = { ...fullAsset };
  } else {
    showDetailModal.value = false;
  }
};

// Close detail modal
const closeDetailModal = () => {
  showDetailModal.value = false;
  selectedAsset.value = null;
  isEditMode.value = false;
  editForm.value = {};
};

// Toggle edit mode
const toggleEditMode = () => {
  isEditMode.value = !isEditMode.value;
  if (isEditMode.value) {
    // Reset form to current values
    editForm.value = { ...selectedAsset.value };
  }
};

// Save changes
const saveChanges = async () => {
  if (!selectedAsset.value) return;

  const result = await Swal.fire({
    title: 'Save Changes?',
    text: 'Are you sure you want to save these changes?',
    icon: 'question',
    showCancelButton: true,
    confirmButtonColor: '#059669',
    cancelButtonColor: '#6b7280',
    confirmButtonText: 'Yes, save it!',
  });

  if (result.isConfirmed) {
    try {
      const payload = {
        person_in_charge: editForm.value.person_in_charge,
        department: editForm.value.department,
        invoice_number: editForm.value.invoice_number,
        invoice_date: editForm.value.invoice_date,
        cost: editForm.value.cost !== undefined && editForm.value.cost !== null ? Number(editForm.value.cost) : null,
        supplier: editForm.value.supplier,
        model_number: editForm.value.model_number,
        specs: editForm.value.specs,
        remarks: editForm.value.remarks,
        date_deployed: editForm.value.date_deployed,
        category_id: editForm.value.category_id,
        company_id: editForm.value.company_id,
        is_active: editForm.value.is_active
      };

      const response = await api.put(`/assets/${selectedAsset.value.id}`, payload);
      
      console.log('Update response:', response.data);

      // Update local data
      selectedAsset.value = { ...editForm.value };
      const index = assets.value.findIndex(a => a.id === selectedAsset.value.id);
      if (index !== -1) {
        assets.value[index] = { ...selectedAsset.value };
      }

      Swal.fire({
        icon: 'success',
        title: 'Saved!',
        text: 'Asset updated successfully',
        timer: 2000,
        showConfirmButton: false
      });

      // Exit edit mode and refresh
      isEditMode.value = false;
      await fetchAssets();
    } catch (err: any) {
      console.error('Failed to update asset', err);
      console.error('Error details:', err.response?.data);
      
      let errorMessage = 'Failed to update asset. Please try again.';
      if (err.response?.data?.message) {
        errorMessage = err.response.data.message;
      }
      
      Swal.fire({
        icon: 'error',
        title: 'Error',
        text: errorMessage
      });
    }
  }
};

// Toggle active/inactive status
const toggleAssetStatus = async () => {
  if (!selectedAsset.value) return;

  const newStatus = selectedAsset.value.is_active ? 0 : 1;
  const statusText = newStatus === 1 ? 'Active' : 'Inactive';

  const result = await Swal.fire({
    title: 'Are you sure?',
    text: `Mark this asset as ${statusText}?`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#059669',
    cancelButtonColor: '#6b7280',
    confirmButtonText: `Yes, mark as ${statusText}!`,
  });

  if (result.isConfirmed) {
    try {
      // Prepare payload with all required fields (same structure as your main form)
      const payload = {
        person_in_charge: selectedAsset.value.person_in_charge,
        department: selectedAsset.value.department,
        invoice_number: selectedAsset.value.invoice_number,
        invoice_date: selectedAsset.value.invoice_date,
        cost: selectedAsset.value.cost !== undefined && selectedAsset.value.cost !== null ? Number(selectedAsset.value.cost) : null,
        supplier: selectedAsset.value.supplier,
        model_number: selectedAsset.value.model_number,
        specs: selectedAsset.value.specs,
        remarks: selectedAsset.value.remarks,
        date_deployed: selectedAsset.value.date_deployed,
        category_id: selectedAsset.value.category_id,
        company_id: selectedAsset.value.company_id,
        is_active: newStatus
      };

      const response = await api.put(`/assets/${selectedAsset.value.id}`, payload);
      
      console.log('Update response:', response.data);

      // Update local data
      selectedAsset.value.is_active = newStatus;
      const index = assets.value.findIndex(a => a.id === selectedAsset.value.id);
      if (index !== -1) {
        assets.value[index].is_active = newStatus;
      }

      Swal.fire({
        icon: 'success',
        title: 'Updated!',
        text: `Asset marked as ${statusText}`,
        timer: 2000,
        showConfirmButton: false
      });

      // Refresh the list
      await fetchAssets();
      closeDetailModal();
    } catch (err: any) {
      console.error('Failed to update asset status', err);
      console.error('Error details:', err.response?.data);
      
      let errorMessage = 'Failed to update asset status. Please try again.';
      if (err.response?.data?.message) {
        errorMessage = err.response.data.message;
      }
      
      Swal.fire({
        icon: 'error',
        title: 'Error',
        text: errorMessage
      });
    }
  }
};
</script>

<template>
<NavBar/>
  <div class="min-h-screen bg-gray-50 pt-20">
    <div class="flex gap-6 p-4 items-start">
      <!-- Sidebar / Filters -->
      <div class="w-80 p-6 bg-white shadow-xl rounded-xl flex-shrink-0">
        <h3 class="text-lg font-semibold mb-4">Filters</h3>

        <!-- Status Filter -->
        <div class="mb-4">
          <label class="block text-sm font-medium mb-1">Status</label>
          <select
            v-model="statusFilter"
            @change="handleSearch"
            class="w-full border border-gray-300 rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500"
          >
            <option value="active">Active Only</option>
            <option value="inactive">Inactive Only</option>
            <option value="all">All Assets</option>
          </select>
        </div>

        <!-- Search Bar -->
        <div class="mb-4">
          <label class="block text-sm font-medium mb-1">Search</label>
          <input 
            v-model="search" 
            @input="handleSearch"
            type="text" 
            placeholder="Search by person in charge or company..." 
            class="w-full border border-gray-300 rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500"
          />
        </div>

        <!-- Items per page -->
        <div class="mb-4">
          <label class="block text-sm font-medium mb-1">Show per page</label>
          <select
            :value="itemsPerPage"
            @change="changeItemsPerPage(Number(($event.target as HTMLSelectElement).value))"
            class="w-full border border-gray-300 rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500"
          >
            <option :value="10">10</option>
            <option :value="25">25</option>
            <option :value="50">50</option>
            <option :value="100">100</option>
          </select>
        </div>

        <!-- Stats -->
        <div class="mt-4 p-3 bg-emerald-50 rounded-lg">
          <p class="text-sm text-emerald-800">
            <span class="font-semibold">{{ filteredAssets.length }}</span>
            {{ filteredAssets.length === 1 ? 'asset' : 'assets' }} found
          </p>
          <p v-if="search || statusFilter !== 'all'" class="text-xs text-emerald-600 mt-1">
            Filtered from {{ assets.length }} total
          </p>
        </div>
      </div>

      <!-- Main Content -->
      <div class="flex-1">
        <!-- Error Display -->
        <div v-if="error" class="mb-6 bg-red-50 border-l-4 border-red-500 p-4 rounded-r-lg shadow-sm">
          <div class="flex items-center">
            <svg class="w-5 h-5 text-red-500 mr-3" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
            </svg>
            <p class="text-red-700 font-medium">{{ error }}</p>
          </div>
        </div>

        <!-- Loading State -->
        <div v-if="loading" class="bg-white rounded-xl shadow-sm p-12 border border-gray-200">
          <div class="flex flex-col items-center justify-center">
            <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-emerald-600 mb-4"></div>
            <p class="text-gray-600">Loading assets...</p>
          </div>
        </div>

        <!-- No Assets -->
        <div v-else-if="!error && assets.length === 0" class="bg-white rounded-xl shadow-sm p-12 border border-gray-200">
          <div class="text-center">
            <svg class="mx-auto h-12 w-12 text-gray-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/>
            </svg>
            <h3 class="text-lg font-medium text-gray-900 mb-2">No assets found</h3>
            <p class="text-gray-600">There are no assets in the database yet.</p>
          </div>
        </div>

        <!-- Table -->
        <div v-else-if="!error" class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
          <!-- No Results -->
          <div v-if="filteredAssets.length === 0" class="p-12 text-center">
            <svg class="mx-auto h-12 w-12 text-gray-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <h3 class="text-lg font-medium text-gray-900 mb-2">No matching results</h3>
            <p class="text-gray-600">Try adjusting your search terms</p>
          </div>

          <!-- Table Content -->
          <div v-else class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
              <thead class="bg-emerald-900 text-white">
                <tr>
                  <th
                    class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider cursor-pointer hover:bg-emerald-800 transition-colors"
                    @click="setSort('person_in_charge')"
                  >
                    <div class="flex items-center gap-2">
                      <span>Person In Charge</span>
                      <span v-if="sortKey === 'person_in_charge'" class="text-white">
                        {{ sortAsc ? '▲' : '▼' }}
                      </span>
                    </div>
                  </th>
                  <th
                    class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider cursor-pointer hover:bg-emerald-800 transition-colors"
                    @click="setSort('company')"
                  >
                    <div class="flex items-center gap-2">
                      <span>Company</span>
                      <span v-if="sortKey === 'company'" class="text-white">
                        {{ sortAsc ? '▲' : '▼' }}
                      </span>
                    </div>
                  </th>
                  <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider">
                    Status
                  </th>
                  <th class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wider">
                    Actions
                  </th>
                </tr>
              </thead>
              <tbody class="bg-white divide-y divide-gray-200">
                <tr
                  v-for="asset in paginatedAssets"
                  :key="asset.id"
                  class="hover:bg-emerald-50 transition-colors"
                >
                  <td class="px-4 py-3 whitespace-nowrap uppercase">
                    <div class="text-sm font-medium text-gray-900">
                      {{ asset.person_in_charge || 'Not Assigned' }}
                    </div>
                  </td>
                  <td class="px-4 py-3 whitespace-nowrap uppercase">
                    <div class="text-sm text-gray-900">{{ asset.company ?? 'N/A' }}</div>
                  </td>
                  <td class="px-4 py-3 whitespace-nowrap">
                    <span 
                      :class="[
                        'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium',
                        (asset.is_active === 1 || asset.is_active === true) 
                          ? 'bg-green-100 text-green-800' 
                          : 'bg-red-100 text-red-800'
                      ]"
                    >
                      {{ (asset.is_active === 1 || asset.is_active === true) ? 'Active' : 'Inactive' }}
                    </span>
                  </td>
                  <td class="px-4 py-3 text-center whitespace-nowrap">
                    <button
                      @click="openDetailModal(asset)"
                      class="bg-emerald-600 hover:bg-emerald-700 text-white px-3 py-1 rounded text-sm font-medium transition-colors"
                    >
                      📋 View Details
                    </button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <!-- Pagination -->
          <div v-if="totalPages > 1" class="bg-gray-50 px-4 py-3 border-t border-gray-200">
            <div class="flex items-center justify-between">
              <!-- Info -->
              <div class="text-sm text-gray-600">
                Showing
                <span class="font-medium">{{ (currentPage - 1) * itemsPerPage + 1 }}</span>
                to
                <span class="font-medium">{{ Math.min(currentPage * itemsPerPage, filteredAssets.length) }}</span>
                of
                <span class="font-medium">{{ filteredAssets.length }}</span>
                results
              </div>

              <!-- Pagination Controls -->
              <div class="flex items-center gap-2">
                <!-- Previous Button -->
                <button
                  @click="goToPage(currentPage - 1)"
                  :disabled="currentPage === 1"
                  :class="[
                    'px-3 py-1 rounded text-sm font-medium transition-all',
                    currentPage === 1
                      ? 'bg-gray-200 text-gray-400 cursor-not-allowed'
                      : 'bg-gray-200 text-gray-700 hover:bg-gray-300'
                  ]"
                >
                  Previous
                </button>

                <!-- Page Info -->
                <div class="px-3 py-1 text-sm text-gray-600">
                  Page {{ currentPage }} of {{ totalPages }}
                </div>

                <!-- Next Button -->
                <button
                  @click="goToPage(currentPage + 1)"
                  :disabled="currentPage === totalPages"
                  :class="[
                    'px-3 py-1 rounded text-sm font-medium transition-all',
                    currentPage === totalPages
                      ? 'bg-gray-200 text-gray-400 cursor-not-allowed'
                      : 'bg-gray-200 text-gray-700 hover:bg-gray-300'
                  ]"
                >
                  Next
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Asset Detail Modal -->
  <div v-if="showDetailModal" class="fixed inset-0 flex items-center justify-center bg-black/50 z-50" @click.self="closeDetailModal">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-3xl p-6 max-h-[90vh] overflow-y-auto">
      <div class="flex justify-between items-start mb-4">
        <h2 class="text-2xl font-bold text-gray-900">
          {{ isEditMode ? 'Edit Asset' : 'Asset Details' }}
        </h2>
        <button @click="closeDetailModal" class="text-gray-400 hover:text-gray-600 text-2xl leading-none">
          ×
        </button>
      </div>

      <!-- Loading State -->
      <div v-if="loadingDetail" class="flex flex-col items-center justify-center py-12">
        <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-emerald-600 mb-4"></div>
        <p class="text-gray-600">Loading asset details...</p>
      </div>

      <!-- Asset Details -->
      <div v-else-if="selectedAsset" class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
        <!-- Person In Charge -->
        <div class="border-b pb-3">
          <label class="block text-xs font-semibold text-gray-500 uppercase mb-1">Person In Charge</label>
          <input
            v-if="isEditMode"
            v-model="editForm.person_in_charge"
            type="text"
            class="w-full border border-gray-300 rounded px-2 py-1 text-sm uppercase"
          />
          <p v-else class="text-sm font-medium text-gray-900 uppercase">{{ selectedAsset.person_in_charge || 'N/A' }}</p>
        </div>

        <!-- Department -->
        <div class="border-b pb-3">
          <label class="block text-xs font-semibold text-gray-500 uppercase mb-1">Department</label>
          <input
            v-if="isEditMode"
            v-model="editForm.department"
            type="text"
            class="w-full border border-gray-300 rounded px-2 py-1 text-sm uppercase"
          />
          <p v-else class="text-sm font-medium text-gray-900 uppercase">{{ selectedAsset.department || 'N/A' }}</p>
        </div>

        <!-- Company -->
        <div class="border-b pb-3">
          <label class="block text-xs font-semibold text-gray-500 uppercase mb-1">Company</label>
          <p class="text-sm font-medium text-gray-900 uppercase">{{ selectedAsset.company?.name || 'N/A' }}</p>
          <p v-if="isEditMode" class="text-xs text-gray-500 mt-1">Cannot change company here</p>
        </div>

        <!-- Category -->
        <div class="border-b pb-3">
          <label class="block text-xs font-semibold text-gray-500 uppercase mb-1">Category</label>
          <p class="text-sm font-medium text-gray-900 uppercase">{{ selectedAsset.category?.name || 'N/A' }}</p>
          <p v-if="isEditMode" class="text-xs text-gray-500 mt-1">Cannot change category here</p>
        </div>

        <!-- Invoice Number -->
        <div class="border-b pb-3">
          <label class="block text-xs font-semibold text-gray-500 uppercase mb-1">Invoice Number</label>
          <input
            v-if="isEditMode"
            v-model="editForm.invoice_number"
            type="text"
            class="w-full border border-gray-300 rounded px-2 py-1 text-sm uppercase"
          />
          <p v-else class="text-sm font-medium text-gray-900 uppercase">{{ selectedAsset.invoice_number || 'N/A' }}</p>
        </div>

        <!-- Invoice Date -->
        <div class="border-b pb-3">
          <label class="block text-xs font-semibold text-gray-500 uppercase mb-1">Invoice Date</label>
          <input
            v-if="isEditMode"
            v-model="editForm.invoice_date"
            type="date"
            class="w-full border border-gray-300 rounded px-2 py-1 text-sm"
          />
          <p v-else class="text-sm font-medium text-gray-900">{{ selectedAsset.invoice_date || 'N/A' }}</p>
        </div>

        <!-- Cost -->
        <div class="border-b pb-3">
          <label class="block text-xs font-semibold text-gray-500 uppercase mb-1">Cost</label>
          <input
            v-if="isEditMode"
            v-model.number="editForm.cost"
            type="number"
            step="0.01"
            class="w-full border border-gray-300 rounded px-2 py-1 text-sm"
          />
          <p v-else class="text-sm font-medium text-gray-900">{{ selectedAsset.cost ? `₱${selectedAsset.cost.toLocaleString()}` : 'N/A' }}</p>
        </div>

        <!-- Supplier -->
        <div class="border-b pb-3">
          <label class="block text-xs font-semibold text-gray-500 uppercase mb-1">Supplier</label>
          <input
            v-if="isEditMode"
            v-model="editForm.supplier"
            type="text"
            class="w-full border border-gray-300 rounded px-2 py-1 text-sm uppercase"
          />
          <p v-else class="text-sm font-medium text-gray-900 uppercase">{{ selectedAsset.supplier || 'N/A' }}</p>
        </div>

        <!-- Model Number -->
        <div class="border-b pb-3">
          <label class="block text-xs font-semibold text-gray-500 uppercase mb-1">Model Number</label>
          <input
            v-if="isEditMode"
            v-model="editForm.model_number"
            type="text"
            class="w-full border border-gray-300 rounded px-2 py-1 text-sm uppercase"
          />
          <p v-else class="text-sm font-medium text-gray-900 uppercase">{{ selectedAsset.model_number || 'N/A' }}</p>
        </div>

        <!-- Date Deployed -->
        <div class="border-b pb-3">
          <label class="block text-xs font-semibold text-gray-500 uppercase mb-1">Date Deployed</label>
          <input
            v-if="isEditMode"
            v-model="editForm.date_deployed"
            type="date"
            class="w-full border border-gray-300 rounded px-2 py-1 text-sm"
          />
          <p v-else class="text-sm font-medium text-gray-900">{{ selectedAsset.date_deployed || 'N/A' }}</p>
        </div>

        <!-- Specification (Full Width) -->
        <div class="border-b pb-3 md:col-span-2">
          <label class="block text-xs font-semibold text-gray-500 uppercase mb-1">Specification</label>
          <textarea
            v-if="isEditMode"
            v-model="editForm.specs"
            rows="3"
            class="w-full border border-gray-300 rounded px-2 py-1 text-sm uppercase resize-y"
          ></textarea>
          <p v-else class="text-sm font-medium text-gray-900 uppercase">{{ selectedAsset.specs || 'N/A' }}</p>
        </div>

        <!-- Remarks (Full Width) -->
        <div class="border-b pb-3 md:col-span-2">
          <label class="block text-xs font-semibold text-gray-500 uppercase mb-1">Remarks</label>
          <textarea
            v-if="isEditMode"
            v-model="editForm.remarks"
            rows="3"
            class="w-full border border-gray-300 rounded px-2 py-1 text-sm uppercase resize-y"
          ></textarea>
          <p v-else class="text-sm font-medium text-gray-900 uppercase">{{ selectedAsset.remarks || 'N/A' }}</p>
        </div>

        <!-- Status -->
        <div class="border-b pb-3">
          <label class="block text-xs font-semibold text-gray-500 uppercase mb-1">Current Status</label>
          <span 
            :class="[
              'inline-flex items-center px-3 py-1 rounded-full text-xs font-medium',
              selectedAsset.is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'
            ]"
          >
            {{ selectedAsset.is_active ? 'Active' : 'Inactive' }}
          </span>
        </div>
      </div>

      <!-- Action Buttons -->
      <div v-if="selectedAsset" class="flex justify-between pt-4 border-t">
        <!-- Left side buttons -->
        <div class="flex gap-3">
          <button 
            v-if="!isEditMode"
            @click="toggleEditMode"
            class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded transition-colors font-medium"
          >
            ✏️ Edit
          </button>
          <button 
            v-if="isEditMode"
            @click="toggleEditMode"
            class="px-4 py-2 bg-gray-200 text-gray-700 rounded hover:bg-gray-300 transition-colors font-medium"
          >
            Cancel Edit
          </button>
        </div>

        <!-- Right side buttons -->
        <div class="flex gap-3">
          <button 
            v-if="!isEditMode"
            @click="closeDetailModal" 
            class="px-4 py-2 bg-gray-200 text-gray-700 rounded hover:bg-gray-300 transition-colors font-medium"
          >
            Close
          </button>
          <button 
            v-if="isEditMode"
            @click="saveChanges"
            class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded transition-colors font-medium"
          >
            💾 Save Changes
          </button>
          <button 
            v-if="!isEditMode"
            @click="toggleAssetStatus"
            :class="[
              'px-4 py-2 rounded font-medium transition-colors',
              selectedAsset.is_active 
                ? 'bg-red-600 hover:bg-red-700 text-white' 
                : 'bg-emerald-600 hover:bg-emerald-700 text-white'
            ]"
          >
            {{ selectedAsset.is_active ? '❌ Mark as Inactive' : '✅ Mark as Active' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>