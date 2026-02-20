<script setup lang="ts">
import NavBar from '@/components/NavBar.vue';
import {ref, onMounted, computed, watch } from 'vue'
import api from '@/services/api';
import Swal from 'sweetalert2';


// User
interface User{
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

interface Department{
    id: number
    name: string
}

const departments = ref<Department[]>([])
const search = ref('')
const newDepartment = ref ('')

// Fetch Department
const fetchDepartments = async () => {
    try {
        const res = await api.get<Department[]>('/departments')
        departments.value = res.data.filter(d => d.name && d.name.trim() !== '')
    } catch (err) {
        console.error(err)
    }
}

// Filtered list for search
const filteredDeparments = computed(() => {
    const q = search.value.toLowerCase()
    return departments.value.filter(d => d.name.toLowerCase(q))
})

// Add department
const addDeparment = async () => {
    const name = newDepartment.value.trim()
    if (!name) return

    try {
        const res = await api.post<Department>('/departments', {name})
        departments.value.push(res.data)
        newDepartment.value = ''
        Swal.fire('Added', 'Department added successfully', 'success')
    } catch (err) {
        Swal.fire('Error', 'Failed to add department', 'error')
    }
}

// Deletion

// confirm deletion
const confirmDelete = (id: number, name: string) => {
    Swal.fire({
        title: `Delete "${name}"?`,
        text: "This action cant be undone",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc2626',
        cancelButtonColor: '#6b7280',
        confirmButtonText: 'Yes, Delete it!',
        cancelButtonText: 'Cancel'
    }). then(async(result) =>{
        if (result.isConfirmed){
            await deleteDepartment(id)
        }
    })
}

// Detele department
const deleteDepartment = async (id: number) => {
    try {
        await api.delete(`/department/${id}`)
        departments.value = departments.value.filter(d => d.id !==id)
    } catch (err) {
        Swal.fire('Error', 'Failed to delete category', 'error')
    }
}
onMounted(fetchDepartments)
</script>

<template>
    <NavBar/>

    <div class = "max-w-lg mx-auto mt-10 bg-white p-6 rounded-xl shadow-lg">
        <!-- header -->
         <h2 class = "text-2xl font-semibold mb-6 text-center">Manage Department</h2>

         <div class="mb-5">
            <input v-model="search" type="text" placeholder="Search Department..."
            class="w-full border border-gray-300 rounded-xl px-4 py-2 focus:ring-2 focus:ring-emerald-400 focus:outline-none transition"/>
         </div>

         <!-- add department -->
          <div class="flex gap-3 bm-6">
            <input v-model="newDepartment" type="text" placeholder="New department"
            class="flex-1 border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focusLring-emerlad-400 focus:outline-none transition"/>
            <button @click="addDeparment"
            class="px-5 py-2 bg-emerald-500 text-white font-medium rounded-xl hover:bg-emerald-600 transition">Add</button>
        </div>

        <!-- department list -->
         <ul class="divide-y divide-gray-200">
            <li v-for="d in filteredDeparments" :key="d.id"
            class="flex justify-between items-center py-3 hover:bg-emerald-50 rounded transition">
            <span class="text-gray-800">{{ d.name }}</span>

            <!-- delete button -->
             <button v-if="user.role === 'admin'" @click="confirmDelete(d.id, d.name)"
             class="px-3 py-1 bg-red-500 rounded hover:bg-red-700 hover:rext-red-900 transtition font-medium">Delete</button>
        </li>
         </ul>
         <p v-if="!filteredDeparments.length" class="text-center text-gray-400 mt-4"> No matching departments</p>
    </div>
</template>