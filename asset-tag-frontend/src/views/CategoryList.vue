<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import NavBar from '@/components/NavBar.vue'
import api from '@/services/api'
import Swal from 'sweetalert2'

interface Category {
  id: number
  name: string
}

const categories = ref<Category[]>([])
const search = ref('')
const newCategory = ref('')

// Fetch categories
const fetchCategories = async () => {
  try {
    const res = await api.get<Category[]>('/categories')
    categories.value = res.data.filter(c => c.name && c.name.trim() !== '')
  } catch (err) {
    console.error(err)
  }
}

// Filtered list for search
const filteredCategories = computed(() => {
  const q = search.value.toLowerCase()
  return categories.value.filter(c => c.name.toLowerCase().includes(q))
})

// Add category
const addCategory = async () => {
  const name = newCategory.value.trim()
  if (!name) return

  try {
    const res = await api.post<Category>('/categories', { name })
    categories.value.push(res.data)
    newCategory.value = ''
    Swal.fire('Added', 'Category added successfully', 'success')
  } catch (err) {
    Swal.fire('Error', 'The name has already been taken', 'error')
  }
}

// Confirm delete
const confirmDelete = (id: number, name: string) => {
  Swal.fire({
    title: `Delete "${name}"?`,
    text: "This action cant be undone",
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#dc2626', // Tailwind red-600
    cancelButtonColor: '#6b7280', // Tailwind gray-500
    confirmButtonText: 'Yes, delete it!',
    cancelButtonText: 'Cancel'
  }).then(async (result) => {
    if (result.isConfirmed) {
      await deleteCategory(id)
    }
  })
}

// Delete category
const deleteCategory = async (id: number) => {
  try {
    await api.delete(`/categories/${id}`)
    categories.value = categories.value.filter(c => c.id !== id)
    Swal.fire('Deleted!', 'Category deleted successfully.', 'success')
  } catch (err) {
    Swal.fire('Error', 'Failed to delete category', 'error')
  }
}

onMounted(fetchCategories)
</script>



<template>
  <NavBar />

  <div class="max-w-lg mx-auto mt-10 bg-white p-6 rounded-xl shadow-lg">
    <!-- Header -->
    <h2 class="text-2xl font-semibold mb-6 text-center">Manage Categories</h2>

    <!-- Search bar -->
    <div class="mb-5">
      <input
        v-model="search"
        type="text"
        placeholder="Search categories..."
        class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-emerald-400 focus:outline-none transition"
      />
    </div>

    <!-- Add category -->
    <div class="flex gap-3 mb-6">
      <input
        v-model="newCategory"
        type="text"
        placeholder="New category name"
        class="flex-1 border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-emerald-400 focus:outline-none transition"
      />
      <button
        @click="addCategory"
        class="px-5 py-2 bg-emerald-500 text-white font-medium rounded-lg hover:bg-emerald-600 transition"
      >
        Add
      </button>
    </div>

    <!-- Category list -->
    <ul class="divide-y divide-gray-200">
      <li
        v-for="c in filteredCategories"
        :key="c.id"
        class="flex justify-between items-center py-3 hover:bg-gray-50 rounded transition"
      >
        <span class="text-gray-800">{{ c.name }}</span>

        <!-- Delete button with confirmation -->
        <button
          @click="confirmDelete(c.id, c.name)"
          class="px-3 py-1 bg-red-100 text-red-600 rounded hover:bg-red-200 hover:text-red-800 transition font-medium"
        >
          Delete
        </button>
      </li>
    </ul>

    <!-- No categories message -->
    <p
      v-if="!filteredCategories.length"
      class="text-center text-gray-400 mt-4"
    >
      No matching categories
    </p>
  </div>
</template>



