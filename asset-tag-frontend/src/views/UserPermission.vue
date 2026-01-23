<script setup lang="ts">
import { ref, onMounted } from 'vue'
import NavBar from '@/components/NavBar.vue'
import api from '@/services/api'
import Swal from 'sweetalert2'

interface User {
  id: number
  name: string
  username: string
  role: 'admin' | 'staff'
}

const users = ref<User[]>([])

const fetchUsers = async () => {
  const res = await api.get('/users')
  users.value = res.data
}

const toggleRole = async (user: User) => {
  const newRole = user.role === 'admin' ? 'staff' : 'admin'

  try {
    await api.patch(`/users/${user.id}/role`, {
      role: newRole
    })

    user.role = newRole

    Swal.fire({
      icon: 'success',
      title: 'Role updated',
      text: `${user.name} is now ${newRole}`,
      timer: 1200,
      showConfirmButton: false
    })
  } catch (err) {
    Swal.fire('Error', 'Failed to update role', 'error')
  }
}


onMounted(fetchUsers)
</script>

<template>
  <NavBar />

  <div class="max-w-5xl mx-auto mt-8 bg-white shadow-lg rounded-xl p-6">
    <h2 class="text-2xl font-bold mb-6 text-gray-800">
      👥 User Role Management
    </h2>

    <div class="overflow-x-auto">
      <table class="w-full border-collapse">
        <thead>
          <tr class="bg-gray-100 text-left text-sm uppercase text-gray-600">
            <th class="px-4 py-3">Name</th>
            <th class="px-4 py-3">Username</th>
            <th class="px-4 py-3 text-center">Role</th>
            <th class="px-4 py-3 text-center">Action</th>
          </tr>
        </thead>

        <tbody>
          <tr
            v-for="u in users"
            :key="u.id"
            class="border-b hover:bg-gray-50 transition"
          >
            <td class="px-4 py-3 font-medium text-gray-800">
              {{ u.name }}
            </td>

            <td class="px-4 py-3 text-gray-600">
              {{ u.username }}
            </td>

            <!-- Role badge -->
            <td class="px-4 py-3 text-center">
              <span
                class="px-3 py-1 rounded-full text-xs font-semibold"
                :class="u.role === 'admin'
                  ? 'bg-emerald-100 text-emerald-700'
                  : 'bg-gray-200 text-gray-700'"
              >
                {{ u.role.toUpperCase() }}
              </span>
            </td>

            <!-- Toggle switch -->
            <td class="px-4 py-3 text-center">
              <label class="inline-flex items-center cursor-pointer">
                <input
                  type="checkbox"
                  class="sr-only"
                  :checked="u.role === 'admin'"
                  @change="toggleRole(u)"
                />
                <div
                  class="w-11 h-6 rounded-full transition"
                  :class="u.role === 'admin'
                    ? 'bg-emerald-500'
                    : 'bg-gray-400'"
                >
                  <div
                    class="w-5 h-5 bg-white rounded-full shadow transform transition"
                    :class="u.role === 'admin'
                      ? 'translate-x-5'
                      : 'translate-x-1'"
                  ></div>
                </div>
              </label>
            </td>
          </tr>

          <tr v-if="users.length === 0">
            <td colspan="4" class="text-center py-6 text-gray-500">
              No users found
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>
